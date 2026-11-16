<?php

use App\Actions\ApplyGenerationSnapshot;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Fixtures/pixelforge.php';

beforeEach(function () {
    config(['services.pixelforge.api_key' => 'report-test-key', 'services.pixelforge.base_url' => 'https://provider.test/v1', 'inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
});

/** @param array<string, array{0: int, 1: string}> $entries */
function reportUsage(string $start, string $end, array $entries = []): array
{
    $days = [];
    $totalCost = '0.000000000';
    $totalCount = 0;
    for ($date = CarbonImmutable::parse($start); $date <= CarbonImmutable::parse($end); $date = $date->addDay()) {
        [$count, $cost] = $entries[$date->toDateString()] ?? [0, '0.000000000'];
        $days[] = ['date' => $date->toDateString(), 'generations' => $count, 'cost_usd' => $cost];
        $totalCost = bcadd($totalCost, $cost, 9);
        $totalCount += $count;
    }

    return ['object' => 'usage', 'start_date' => $start, 'end_date' => $end, 'data' => $days, 'total' => ['generations' => $totalCount, 'cost_usd' => $totalCost]];
}

function reportSettlement(User $user, string $completed, string $cost, string $charge, string $outcome = 'succeeded'): Generation
{
    return Generation::factory()->for($user)->accepted()->has(
        GenerationSettlement::factory()->state(['outcome' => $outcome, 'provider_cost' => $cost, 'user_charge' => $charge]),
        'settlement',
    )->create(['status' => $outcome, 'provider_completed_at' => $completed, 'next_attempt_at' => null]);
}

test('guests cannot access the financial report', function () {
    $this->get(route('admin.report'))->assertRedirect(route('login'));
    Http::assertNothingSent();
});

test('team owners cannot access the platform report', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('admin.report'))->assertForbidden();
    Http::assertNothingSent();
});

test('only verified platform administrators have the report ability', function (bool $admin, bool $verified, bool $allowed) {
    $user = User::factory()->create(['is_admin' => $admin, 'email_verified_at' => $verified ? now() : null]);

    expect(Gate::forUser($user)->allows('view-admin-report'))->toBe($allowed);
})->with([[false, true, false], [true, false, false], [true, true, true]]);

test('unverified administrators must verify their email', function () {
    $user = User::factory()->admin()->unverified()->create();

    $this->actingAs($user)->get(route('admin.report'))->assertRedirect(route('verification.notice'));
    Http::assertNothingSent();
});

test('reports exact daily and user totals by UTC completion date and retains deleted users costs', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17T08:00:00Z'));
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->create(['name' => 'Alice']);
    $deleted = User::factory()->create();
    reportSettlement($alice, '2026-11-15 23:59:59', '0.000000001000000000', '0.000000001250000000');
    reportSettlement($alice, '2026-11-16 00:00:00', '0.100000000000000000', '0.125000000000000000');
    reportSettlement($deleted, '2026-11-16 12:00:00', '0.200000000000000000', '0.250000000000000000');
    reportSettlement($alice, '2026-11-16 13:00:00', '0.000000000000000000', '0.000000000000000000', 'failed');
    reportSettlement($alice, '2026-11-17 00:00:00', '1.000000000000000000', '1.250000000000000000');
    reportSettlement($alice, '2026-11-14 23:59:59', '2.000000000000000000', '2.500000000000000000');
    Generation::factory()->for($alice)->uncertain()->create(['next_attempt_at' => null]);
    GenerationSettlement::factory()->create();
    $deleted->delete();
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-15', '2026-11-16', [
        '2026-11-15' => [1, '0.000000001'], '2026-11-16' => [2, '0.300000000'],
    ]))]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-15', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('AdminReport')
        ->where('auth.canViewAdminReport', true)
        ->where('report.totals', ['generations' => 3, 'failed' => 1, 'user_charge' => '0.375000001250000000', 'provider_cost' => '0.300000001000000000', 'profit' => '0.075000000250000000'])
        ->where('report.days.0.date', '2026-11-16')
        ->where('report.days.1.amounts.provider_cost', '0.000000001000000000')
        ->where('report.reconciliation.status', 'matched')
        ->where('report.reconciliation.total.cost_difference', '0.000000000000000000')
        ->where('report.unresolved_generations', 1)
        ->where('users.total', 2)
        ->where('users.data.0.name', 'Deleted users')
        ->where('users.data.0.amounts.user_charge', '0.250000000000000000')
        ->where('users.data.1.id', $alice->id)
        ->has('users.data.1.days', 2));
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer report-test-key') && $request['start_date'] === '2026-11-15' && $request['end_date'] === '2026-11-16');
});

test('repeated successful snapshots appear only once in the report', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16T12:00:00Z'));
    $admin = User::factory()->admin()->create();
    $generation = Generation::factory()->for($admin)->create();
    $snapshot = pixelforgeSnapshot($generation);
    app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);
    app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-16', '2026-11-16', ['2026-11-16' => [1, '0.305510000']]))]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.totals.generations', 1)
        ->where('report.totals.user_charge', '0.381887500000000000')
        ->where('report.reconciliation.status', 'matched'));
    Http::assertSentCount(1);
});

test('detects daily differences even when period totals cancel out', function () {
    $admin = User::factory()->admin()->create();
    reportSettlement($admin, '2026-11-15 12:00:00', '0.100000000000000000', '0.125000000000000000');
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-15', '2026-11-16', ['2026-11-16' => [1, '0.100000000']]))]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-15', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'mismatch')
        ->where('report.reconciliation.total.cost_difference', '0.000000000000000000')
        ->where('report.days.0.reconciliation.cost_difference', '-0.100000000000000000')
        ->where('report.days.1.reconciliation.cost_difference', '0.100000000000000000'));
    Http::assertSentCount(1);
});

test('detects count mismatches even when costs match', function () {
    $admin = User::factory()->admin()->create();
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-16', '2026-11-16', ['2026-11-16' => [1, '0.000000000']]))]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.status', 'mismatch')->where('report.reconciliation.total.generation_difference', -1));
    Http::assertSentCount(1);
});

test('retains all fractional digits instead of rounding away a mismatch', function () {
    $admin = User::factory()->admin()->create();
    reportSettlement($admin, '2026-11-16 12:00:00', '0.100000000000000001', '0.125000000000000001');
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-16', '2026-11-16', ['2026-11-16' => [1, '0.100000000']]))]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.status', 'mismatch')->where('report.reconciliation.total.cost_difference', '0.000000000000000001'));
    Http::assertSentCount(1);
});

test('rejects invalid report filters before calling the provider', function (array $filters, string $field, string $message) {
    $this->travelTo(CarbonImmutable::parse('2026-11-17T08:00:00Z'));
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson(route('admin.report', $filters))->assertUnprocessable()->assertInvalid([$field => $message]);
    Http::assertNothingSent();
})->with([
    'invalid date' => [['start_date' => '2026-02-30'], 'start_date', 'must match the format'],
    'empty date' => [['start_date' => ''], 'start_date', 'required'],
    'reversed dates' => [['start_date' => '2026-11-16', 'end_date' => '2026-11-15'], 'end_date', 'after or equal'],
    'too many days' => [['start_date' => '2026-08-16', 'end_date' => '2026-11-16'], 'end_date', '92 days or fewer'],
    'future' => [['end_date' => '2026-11-18'], 'end_date', 'before or equal'],
    'invalid page' => [['page' => 0], 'page', 'at least 1'],
    'array date' => [['start_date' => ['bad']], 'start_date', 'must match the format'],
]);

test('accepts an inclusive 92 day range with zero usage days', function () {
    $admin = User::factory()->admin()->create();
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-08-17', '2026-11-16'))]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-08-17', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('report.days', 92)->where('users.total', 0)->where('report.reconciliation.status', 'matched'));
    Http::assertSentCount(1);
});

test('defaults to the last 30 UTC days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17T00:15:00Z'));
    $admin = User::factory()->admin()->create();
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-10-19', '2026-11-17'))]);

    $this->actingAs($admin)->get(route('admin.report'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['start_date' => '2026-10-19', 'end_date' => '2026-11-17'])->has('report.days', 30));
    Http::assertSentCount(1);
});

test('keeps local figures when provider reconciliation is unavailable', function (string $failure) {
    $admin = User::factory()->admin()->create();
    reportSettlement($admin, '2026-11-16 12:00:00', '0.100000000000000000', '0.125000000000000000');
    Http::fake(['https://provider.test/v1/usage*' => match ($failure) {
        'timeout' => Http::failedConnection(),
        'invalid json' => Http::response('not json', 200),
        default => Http::response(['error' => ['code' => $failure]], $failure === 'invalid_api_key' ? 401 : 503),
    }]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.totals.user_charge', '0.125000000000000000')
        ->where('report.reconciliation.status', 'unavailable')->where('report.reconciliation.total', null)
        ->where('report.days.0.reconciliation', null));
    Http::assertSentCount(1);
})->with(['timeout', 'invalid_api_key', 'overloaded', 'invalid json']);

test('rejects incomplete or inconsistent provider usage', function (string $defect) {
    $admin = User::factory()->admin()->create();
    $usage = reportUsage('2026-11-15', '2026-11-16');
    match ($defect) {
        'missing day' => array_pop($usage['data']),
        'duplicate day' => $usage['data'][1]['date'] = '2026-11-15',
        'wrong day' => $usage['data'][0]['date'] = '2026-11-14',
        'wrong range' => $usage['start_date'] = '2026-11-14',
        'wrong total' => $usage['total']['cost_usd'] = '0.000000001',
        'wrong count' => $usage['total']['generations'] = 1,
        'float money' => $usage['data'][0]['cost_usd'] = 0.0,
        'negative money' => $usage['data'][0]['cost_usd'] = '-0.100000000',
    };
    Http::fake(['https://provider.test/v1/usage*' => Http::response($usage)]);

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-15', 'end_date' => '2026-11-16']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'unavailable')
        ->where('report.reconciliation.message', 'Pixelforge returned incomplete or inconsistent usage data. Try refreshing the report later.'));
    Http::assertSentCount(1);
})->with(['missing day', 'duplicate day', 'wrong day', 'wrong range', 'wrong total', 'wrong count', 'float money', 'negative money']);

test('honors Retry-After before making another usage request', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    Http::fake(['https://provider.test/v1/usage*' => Http::sequence()
        ->push(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '120'])
        ->push(reportUsage('2026-11-16', '2026-11-16'))]);
    $url = route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']);

    $this->actingAs($admin)->get($url)->assertOk();
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.status', 'unavailable'));
    Http::assertSentCount(1);
    $this->travel(120)->seconds();
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.status', 'matched'));
    Http::assertSentCount(2);
});

test('uses the shared provider request budget', function () {
    $admin = User::factory()->admin()->create();
    RateLimiter::increment('pixelforge:requests', 60, 45);

    $this->actingAs($admin)->get(route('admin.report'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.status', 'unavailable'));
    Http::assertNothingSent();
});

test('does not call the provider without a configured key', function () {
    config(['services.pixelforge.api_key' => null]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.report'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('report.reconciliation.status', 'unavailable'));
    Http::assertNothingSent();
});

test('paginates user totals without changing the financial totals', function () {
    config(['services.pixelforge.api_key' => null]);
    $admin = User::factory()->admin()->create();
    foreach (User::factory()->count(26)->create() as $user) {
        reportSettlement($user, '2026-11-16 12:00:00', '0.100000000000000000', '0.125000000000000000');
    }

    $this->actingAs($admin)->get(route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.total', 26)
        ->where('report.totals.user_charge', '3.250000000000000000')
        ->where('users.prev_page_url', route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'page' => 1])));
    Http::assertNothingSent();
});

test('reuses verified usage across user pages for thirty seconds without freezing local totals', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-16', '2026-11-16'))]);
    $filters = ['start_date' => '2026-11-16', 'end_date' => '2026-11-16'];
    $checkedAt = now()->toIso8601String();

    $this->actingAs($admin)->get(route('admin.report', $filters))->assertOk();
    reportSettlement($admin, '2026-11-16 12:00:00', '0.100000000000000000', '0.125000000000000000');
    $this->travel(29)->seconds();
    $this->get(route('admin.report', [...$filters, 'page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.totals.generations', 1)
        ->where('report.reconciliation.checked_at', $checkedAt));
    Http::assertSentCount(1);

    $this->travel(1)->seconds();
    $this->get(route('admin.report', $filters))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.checked_at', now()->toIso8601String()));
    Http::assertSentCount(2);
});

test('isolates cached usage by provider endpoint key and date range', function (string $change) {
    $this->travelTo(CarbonImmutable::parse('2026-11-17T08:00:00Z'));
    $admin = User::factory()->admin()->create();
    Http::fake([
        'https://provider.test/v1/usage*' => fn (Request $request) => Http::response(reportUsage($request['start_date'], $request['end_date'])),
        'https://other-provider.test/v1/usage*' => fn (Request $request) => Http::response(reportUsage($request['start_date'], $request['end_date'])),
    ]);
    $filters = ['start_date' => '2026-11-16', 'end_date' => '2026-11-16'];
    $this->actingAs($admin)->get(route('admin.report', $filters))->assertOk();
    match ($change) {
        'key' => config(['services.pixelforge.api_key' => 'another-key']),
        'endpoint' => config(['services.pixelforge.base_url' => 'https://other-provider.test/v1']),
        'start date' => $filters['start_date'] = '2026-11-15',
        'end date' => $filters['end_date'] = '2026-11-17',
    };

    $this->get(route('admin.report', $filters))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'matched'));

    Http::assertSentCount(2);
})->with(['key', 'endpoint', 'start date', 'end date']);

test('does not cache invalid usage or connection failures', function (string $failure) {
    $admin = User::factory()->admin()->create();
    $responses = Http::sequence();
    if ($failure === 'invalid usage') {
        $responses->push(['object' => 'usage']);
    } else {
        $responses->pushFailedConnection();
    }
    $responses->push(reportUsage('2026-11-16', '2026-11-16'));
    Http::fake(['https://provider.test/v1/usage*' => $responses]);
    $url = route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']);
    $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'unavailable'));

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'matched'));

    Http::assertSentCount(2);
})->with(['invalid usage', 'connection failure']);

test('retries usage as soon as local capacity is available', function (string $limit) {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    Http::fake(['https://provider.test/v1/usage*' => Http::response(reportUsage('2026-11-16', '2026-11-16'))]);
    $lock = Cache::lock('pixelforge:request-budget-lock', 5);
    if ($limit === 'lock') {
        $lock->get();
    } else {
        RateLimiter::increment('pixelforge:requests', 60, 45);
    }
    $url = route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-16']);
    $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'unavailable'));
    Http::assertNothingSent();
    if ($limit === 'lock') {
        $lock->release();
    } else {
        RateLimiter::clear('pixelforge:requests');
    }

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('report.reconciliation.status', 'matched'));

    Http::assertSentCount(1);
})->with(['lock', 'budget']);
