<?php

use App\Actions\ApplyGenerationSnapshot;
use App\Enums\TeamRole;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Fixtures/pixelforge.php';

beforeEach(function () {
    config(['services.pixelforge.api_key' => 'test-key', 'inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
});

test('guests cannot estimate or submit images', function () {
    $this->postJson(route('generations.estimate'), [])->assertUnauthorized();
    $this->postJson(route('generations.store'), [])->assertUnauthorized();
    $this->assertDatabaseCount('generations', 0);
});

test('estimates must have valid image settings', function () {
    $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'prompt' => '', 'size' => 'invalid', 'quality' => 'ultra', 'reference_images' => ['javascript:alert(1)'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['prompt', 'size', 'quality', 'reference_images.0']);
    Http::assertNothingSent();
});

test('submission persists a signed estimate and ignores client money or ownership', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    $input = ['prompt' => 'A quiet mountain lake', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => []];
    $quote = $this->actingAs($user)->postJson(route('generations.estimate'), $input)->assertOk()->json('quote');
    $request = [...$input, 'quote' => $quote, 'client_request_id' => (string) Str::uuid(), 'user_id' => 999, 'estimated_user_cost' => 0];

    $response = $this->postJson(route('generations.store'), $request)->assertAccepted()->assertJsonPath('data.actual_cost', null);
    $this->postJson(route('generations.store'), $request)->assertAccepted()->assertJsonPath('data.id', $response->json('data.id'));

    $this->assertDatabaseCount('generations', 1);
    $this->assertDatabaseHas('generations', ['id' => $response->json('data.id'), 'user_id' => $user->id, 'estimated_user_cost' => '0.072225000000000000']);
    Queue::assertPushed(SyncGeneration::class, fn ($job) => $job->generationId === $response->json('data.id'));
    Http::assertSentCount(1);
});

test('changed input cannot reuse an existing submission id', function () {
    $generation = Generation::factory()->create();
    $input = $generation->request_payload;
    $input['prompt'] = 'Changed prompt';

    $this->actingAs($generation->user)->postJson(route('generations.store'), [
        ...$input, 'client_request_id' => $generation->client_request_id, 'quote' => 'unused',
    ])->assertConflict();
    $this->assertDatabaseCount('generations', 1);
    Http::assertNothingSent();
});

test('invalid or expired estimates cannot create generations', function (bool $expire) {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $this->freezeTime();
    $input = ['prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => []];
    $quote = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), $input)->assertOk()->json('quote');
    if ($expire) {
        $this->travel(6)->minutes();
    } else {
        $quote = 'tampered';
    }

    $this->postJson(route('generations.store'), [...$input, 'client_request_id' => (string) Str::uuid(), 'quote' => $quote])
        ->assertUnprocessable()->assertJsonValidationErrors('quote');

    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
})->with([true, false]);

test('history and recovery are private to the owner even within a shared team', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $owner->currentTeam->members()->attach($other, ['role' => TeamRole::Member->value]);
    $other->switchTeam($owner->currentTeam);
    $generation = Generation::factory()->for($owner)->create();

    $this->actingAs($other)->get(route('dashboard', ['current_team' => $other->currentTeam->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('generations.data', 0));
    $this->postJson(route('generations.resume', $generation))->assertNotFound();
    Http::assertNothingSent();
});

test('resuming an uncertain generation preserves its original identity', function () {
    Queue::fake([SyncGeneration::class]);
    $generation = Generation::factory()->uncertain()->create(['next_attempt_at' => null, 'submit_attempts' => 5, 'capacity_wait_started_at' => now()->subMinutes(20)]);

    $this->actingAs($generation->user)->postJson(route('generations.resume', $generation))->assertAccepted();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'submission_state' => 'uncertain', 'submit_attempts' => 0, 'capacity_wait_started_at' => null]);
    Queue::assertPushed(SyncGeneration::class, fn ($job) => $job->generationId === $generation->id);
    Http::assertNothingSent();
});

test('a signed quote belongs to the user who requested it', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $input = ['prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => []];
    $quote = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), $input)->assertOk()->json('quote');

    $this->actingAs(User::factory()->create())->postJson(route('generations.store'), [...$input, 'client_request_id' => (string) Str::uuid(), 'quote' => $quote])
        ->assertUnprocessable()->assertJsonValidationErrors('quote');

    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
});

test('a price change requires a fresh estimate before submission', function () {
    Queue::fake([SyncGeneration::class]);
    $this->freezeTime();
    $changed = pixelforgePricing();
    $changed['rates']['output_tokens'] = '80.00';
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::sequence()->push(pixelforgePricing())->push($changed)]);
    $input = ['prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => []];
    $quote = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), $input)->assertOk()->json('quote');
    $this->travel(61)->seconds();

    $this->postJson(route('generations.store'), [...$input, 'client_request_id' => (string) Str::uuid(), 'quote' => $quote])
        ->assertUnprocessable()->assertJsonValidationErrors('quote');

    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(2);
});

test('paused generations remain visible to refresh until a late result completes them', function () {
    $generation = Generation::factory()->uncertain()->create(['next_attempt_at' => null]);
    $url = route('dashboard', ['current_team' => $generation->user->currentTeam->slug]);

    $this->actingAs($generation->user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('hasActiveGenerations', false)->where('hasPausedGenerations', true)
        ->where('generations.data.0.status', 'queued'));
    app(ApplyGenerationSnapshot::class)->handle($generation->id, pixelforgeSnapshot($generation));
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('hasActiveGenerations', false)->where('hasPausedGenerations', false)
        ->where('generations.data.0.status', 'succeeded'));

    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertNothingSent();
});

test('malformed provider pricing returns a generic 503 instead of user validation errors', function (string $problem) {
    $pricing = pixelforgePricing();
    match ($problem) {
        'missing output' => data_forget($pricing, 'tokens.output.medium.1024x1024'),
        'missing rate' => data_forget($pricing, 'rates.reasoning_tokens'),
        'invalid rate' => data_set($pricing, 'rates.output_tokens', '1e3'),
        'inverted range' => data_set($pricing, 'tokens.reasoning.medium.max', 1),
        'invalid variance' => data_set($pricing, 'tokens.text_token_variance', -1),
    };
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response($pricing)]);

    $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => [],
    ])->assertServiceUnavailable()->assertExactJson(['message' => 'Image pricing is temporarily unavailable. Please try again.']);

    $this->assertDatabaseCount('generations', 0);
    Http::assertSentCount(1);
})->with(['missing output', 'missing rate', 'invalid rate', 'inverted range', 'invalid variance']);

test('the estimate endpoint returns both exact prices and signs the upper estimate', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'prompt' => str_repeat('a', 400), 'size' => '1536x1024', 'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ])->assertOk()->assertJsonPath('estimated_cost', '0.412562500000000000')
        ->assertJsonPath('maximum_cost', '0.467343750000000000');

    $signed = json_decode(Crypt::decryptString($response->json('quote')), true);
    expect($signed['estimate']['maximum_user_cost'])->toBe('0.467343750000000000');
    Http::assertSentCount(1);
});

test('a non-JSON pricing response returns a useful 503', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response('<html>Unavailable</html>', 503)]);

    $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => [],
    ])->assertServiceUnavailable()->assertExactJson(['message' => 'Image pricing is temporarily unavailable. Please try again.']);

    Http::assertSentCount(1);
});

test('broken pricing during submission creates no generation and returns 503', function () {
    $this->freezeTime();
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::sequence()->push(pixelforgePricing())->push(['currency' => 'usd'])]);
    $input = ['prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => []];
    $quote = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), $input)->assertOk()->json('quote');
    $this->travel(61)->seconds();

    $this->postJson(route('generations.store'), [...$input, 'client_request_id' => (string) Str::uuid(), 'quote' => $quote])
        ->assertServiceUnavailable()->assertExactJson(['message' => 'Image pricing is temporarily unavailable. Please try again.']);

    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(2);
});
