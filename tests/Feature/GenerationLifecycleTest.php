<?php

use App\Actions\ApplyGenerationSnapshot;
use App\Actions\PixelforgeClient;
use App\Jobs\ProcessPixelforgeWebhook;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/../Fixtures/pixelforge.php';

beforeEach(function () {
    config(['services.pixelforge.api_key' => 'test-key', 'queue.default' => 'database']);
    Http::preventStrayRequests();
});

test('repeated results create one exact settlement and cannot regress the status', function () {
    $generation = Generation::factory()->accepted()->create();
    $apply = app(ApplyGenerationSnapshot::class);
    $success = pixelforgeSnapshot($generation);

    $apply->handle($generation->id, $success);
    $apply->handle($generation->id, $success);
    $apply->handle($generation->id, pixelforgeSnapshot($generation, 'processing'));
    $apply->handle($generation->id, pixelforgeSnapshot($generation, 'queued'));

    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'provider_cost' => '0.305510000000000000', 'user_charge' => '0.381887500000000000']);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded']);
    Http::assertNothingSent();
});

test('provider failure records a zero settlement', function () {
    $generation = Generation::factory()->accepted()->create();

    app(ApplyGenerationSnapshot::class)->handle($generation->id, pixelforgeSnapshot($generation, 'failed'));

    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'provider_cost' => '0.000000000000000000', 'user_charge' => '0.000000000000000000']);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'failed']);
});

test('a success without complete usage rolls back rather than settling for zero', function () {
    $generation = Generation::factory()->accepted()->create();
    $snapshot = pixelforgeSnapshot($generation);
    unset($snapshot['usage']['reasoning_tokens']);

    expect(fn () => app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('generation_settlements', 0);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued']);
});

test('a response for another local generation is rejected', function () {
    $generation = Generation::factory()->accepted()->create();
    $other = Generation::factory()->accepted()->create();

    expect(fn () => app(ApplyGenerationSnapshot::class)->handle($generation->id, pixelforgeSnapshot($other)))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('creation uses the same idempotency key after a lost response', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    $success = pixelforgeSnapshot($generation);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::sequence()->pushFailedConnection()->push($success, 202)]);
    $job = new SyncGeneration($generation->id);

    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'submission_state' => 'uncertain', 'status' => 'queued']);
    $this->travel(1)->minutes();
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $generation->id) && $request->data() === $generation->request_payload);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded']);
});

test('an early success is not overwritten by the late create response', function () {
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => function () use ($generation) {
        app(ApplyGenerationSnapshot::class)->handle($generation->id, pixelforgeSnapshot($generation));

        return Http::response(pixelforgeSnapshot($generation, 'queued'), 202);
    }]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'submission_state' => 'accepted']);
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertSentCount(1);
});

test('temporary provider limits honor Retry-After without marking a generation failed', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '120'])]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    expect($generation->refresh()->next_attempt_at->timestamp)->toBe(now()->addSeconds(120)->timestamp);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued', 'submission_state' => 'pending']);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertSentCount(1);
});

test('definitive submission rejection is free', function (string $code, int $status) {
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => $code]], $status)]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'failed']);
    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'user_charge' => '0.000000000000000000']);
    Http::assertSentCount(1);
})->with([['content_policy_violation', 400], ['daily_limit_exceeded', 429]]);

test('retry exhaustion after an ambiguous response remains recoverable', function () {
    $generation = Generation::factory()->uncertain()->create(['submit_attempts' => 4]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => 'overloaded']], 503)]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued', 'submission_state' => 'uncertain', 'next_attempt_at' => null]);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertSentCount(1);
});

test('polling recovers missed webhooks and uses the original rate snapshot', function () {
    $generation = Generation::factory()->accepted()->create();
    $snapshot = pixelforgeSnapshot($generation);
    $snapshot['rates']['output_tokens'] = '20.00';
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations/'.$generation->provider_generation_id => Http::response($snapshot)]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'provider_cost' => '0.176510000000000000', 'user_charge' => '0.220637500000000000']);
    Http::assertSentCount(1);
});

test('recovery command restores database work whose queue dispatch was lost', function () {
    Queue::fake([SyncGeneration::class, ProcessPixelforgeWebhook::class]);
    $generation = Generation::factory()->create();
    $event = WebhookEvent::factory()->create();

    $this->artisan('generations:recover')->assertSuccessful();

    Queue::assertPushed(SyncGeneration::class, fn ($job) => $job->generationId === $generation->id);
    Queue::assertPushed(ProcessPixelforgeWebhook::class, fn ($job) => $job->eventId === $event->id);
});

test('local capacity waits do not consume attempts or contact the provider', function (string $reason) {
    $this->freezeTime();
    $generation = Generation::factory()->create(['submit_attempts' => 4]);
    if ($reason === 'budget') {
        RateLimiter::increment('pixelforge:requests', 60, 45);
    } else {
        Cache::lock('pixelforge:request-budget-lock', 5)->get();
    }

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'submit_attempts' => 4, 'status' => 'queued', 'submission_state' => 'pending', 'error_code' => 'local_rate_limit']);
    expect($generation->refresh()->next_attempt_at)->toBeGreaterThan(now());
    expect($generation->capacity_wait_started_at->timestamp)->toBe(now()->timestamp);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertNothingSent();
})->with(['budget', 'lock']);

test('provider capacity limits preserve the retry budget and previous submission certainty', function (string $code, bool $uncertain) {
    $this->freezeTime();
    $generation = Generation::factory()->create(['submit_attempts' => 4, 'submission_state' => $uncertain ? 'uncertain' : 'pending']);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => $code]], 429, ['Retry-After' => '120'])]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'submit_attempts' => 4, 'status' => 'queued', 'submission_state' => $uncertain ? 'uncertain' : 'pending']);
    expect($generation->refresh()->next_attempt_at->timestamp)->toBe(now()->addSeconds(120)->timestamp);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertSentCount(1);
})->with(['rate_limit_exceeded', 'concurrency_limit_exceeded'])->with([false, true]);

test('concurrency waits schedule a prompt retry even after a long wait', function (int $waitingSeconds, int $retryAfter, int $minimumDelay, int $maximumDelay) {
    $this->freezeTime();
    $generation = Generation::factory()->create([
        'submit_attempts' => 4,
        'capacity_wait_started_at' => now()->subSeconds($waitingSeconds),
    ]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::sequence()
        ->push(['error' => ['code' => 'concurrency_limit_exceeded']], 429, ['Retry-After' => (string) $retryAfter])
        ->push(pixelforgeSnapshot($generation), 202)]);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);

    $queue->pop()->fire();

    $generation->refresh();
    $delay = $generation->next_attempt_at->timestamp - now()->timestamp;
    expect($delay)->toBeBetween($minimumDelay, $maximumDelay);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'submit_attempts' => 4, 'status' => 'queued', 'submission_state' => 'pending']);
    expect($generation->capacity_wait_started_at->timestamp)->toBe(now()->subSeconds($waitingSeconds)->timestamp);
    $this->assertDatabaseHas('jobs', ['available_at' => $generation->next_attempt_at->timestamp]);
    $this->assertDatabaseCount('generation_settlements', 0);
    expect($queue->pop())->toBeNull();

    $this->travelTo($generation->next_attempt_at);
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'submit_attempts' => 5, 'capacity_wait_started_at' => null]);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(2);
})->with([
    'initial wait' => [0, 5, 5, 8],
    'no retry header after two minutes' => [120, 0, 5, 8],
    'still quick after ten minutes' => [600, 5, 5, 8],
    'longer provider retry interval' => [120, 120, 120, 120],
]);

test('a concurrency retry crossing the capacity deadline pauses without another provider request', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create(['capacity_wait_started_at' => now()->subSeconds(898)]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => 'concurrency_limit_exceeded']], 429, ['Retry-After' => '120'])]);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);

    $queue->pop()->fire();

    $this->assertDatabaseHas('jobs', ['available_at' => now()->addSeconds(2)->timestamp]);
    $this->travel(2)->seconds();
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued', 'submission_state' => 'pending', 'submit_attempts' => 0, 'next_attempt_at' => null, 'error_code' => 'capacity_wait_expired']);
    $this->assertDatabaseCount('generation_settlements', 0);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(1);
});

test('capacity waiting stops after fifteen minutes without declaring a provider failure', function () {
    $this->freezeTime();
    $generation = Generation::factory()->uncertain()->create(['capacity_wait_started_at' => now()->subSeconds(901)]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued', 'submission_state' => 'uncertain', 'next_attempt_at' => null, 'error_code' => 'capacity_wait_expired']);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertNothingSent();
});

test('polling capacity waits retain the accepted generation and poll budget', function () {
    $generation = Generation::factory()->accepted()->create(['poll_attempts' => 19]);
    RateLimiter::increment('pixelforge:requests', 60, 45);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'poll_attempts' => 19, 'submission_state' => 'accepted', 'status' => 'queued']);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertNothingSent();
});

test('exhausted overload retries clearly stop instead of promising another retry', function () {
    $generation = Generation::factory()->create(['submit_attempts' => 4]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => 'overloaded']], 503)]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'failed', 'next_attempt_at' => null, 'error_message' => 'The provider remained unavailable after repeated attempts. No image was created. Please try again later.']);
    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'user_charge' => '0.000000000000000000']);
    Http::assertSentCount(1);
});

test('delayed retries survive unique job locks and run before the recovery scheduler', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::sequence()
        ->push(['error' => ['code' => 'overloaded']], 503, ['Retry-After' => '2'])
        ->push(pixelforgeSnapshot($generation))]);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);
    SyncGeneration::dispatch($generation->id);
    $this->assertDatabaseCount('jobs', 1);

    $queue->pop()->fire();

    $this->assertDatabaseHas('jobs', ['available_at' => now()->addSeconds(5)->timestamp, 'attempts' => 0]);
    SyncGeneration::dispatch($generation->id);
    $this->assertDatabaseCount('jobs', 1);
    expect($queue->pop())->toBeNull();
    $this->travel(5)->seconds();
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'submit_attempts' => 2]);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $generation->id));
});

test('delayed polling completes an accepted generation without waiting for the scheduler', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeSnapshot($generation, 'queued')),
        'https://pixelforge.promptedit.dev/v1/generations/gen_test' => Http::response(pixelforgeSnapshot($generation)),
    ]);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);

    $queue->pop()->fire();

    $this->assertDatabaseHas('jobs', ['available_at' => now()->addSeconds(45)->timestamp]);
    expect($queue->pop())->toBeNull();
    $this->travel(45)->seconds();
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'submit_attempts' => 1, 'poll_attempts' => 1]);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(2);
});

test('a late webhook makes an already queued retry harmless', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::failedConnection()]);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);
    $queue->pop()->fire();

    app(ApplyGenerationSnapshot::class)->handle($generation->id, pixelforgeSnapshot($generation));
    $this->travel(15)->seconds();
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded']);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(1);
});

test('capacity waits can outlast the job attempt limit but stop at their deadline', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    $deadline = now()->addMinutes(15);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);

    for ($attempt = 0; $attempt < 6; $attempt++) {
        Cache::lock('pixelforge:request-budget-lock', 5)->get();
        $queue->pop()->fire();
        $generation->refresh();
        $this->travelTo($generation->next_attempt_at);
    }

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued', 'submit_attempts' => 0, 'submission_state' => 'pending']);
    $this->assertDatabaseCount('jobs', 1);
    $this->travelTo($deadline);
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'next_attempt_at' => null, 'error_code' => 'capacity_wait_expired']);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
    Http::assertNothingSent();
});

test('definitive errors after an uncertain submission do not finalize it as failed', function (string $code, int $status) {
    $generation = Generation::factory()->uncertain()->create(['submit_attempts' => 4]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => $code]], $status)]);

    (new SyncGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued', 'submission_state' => 'uncertain', 'next_attempt_at' => null]);
    $this->assertDatabaseCount('generation_settlements', 0);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(1);
})->with([['content_policy_violation', 400], ['validation_failed', 422], ['daily_limit_exceeded', 429]]);

test('an early duplicate preserves the scheduled retry without contacting the provider early', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create(['next_attempt_at' => now()->addSeconds(20)]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeSnapshot($generation))]);
    $queue = Queue::connection('database');
    SyncGeneration::dispatch($generation->id);

    $queue->pop()->fire();

    $this->assertDatabaseHas('jobs', ['available_at' => now()->addSeconds(20)->timestamp]);
    Http::assertNothingSent();
    $this->travel(20)->seconds();
    $queue->pop()->fire();

    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'submit_attempts' => 1]);
    $this->assertDatabaseCount('jobs', 0);
    Http::assertSentCount(1);
});
