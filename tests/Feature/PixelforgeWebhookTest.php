<?php

use App\Actions\ApplyGenerationSnapshot;
use App\Actions\GenerationPricing;
use App\Http\Resources\GenerationResource;
use App\Jobs\ProcessPixelforgeWebhook;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Fixtures/pixelforge.php';

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => 'whsec_test']);
});

test('invalid or stale webhook signatures never persist an event', function (string $mode) {
    $this->freezeTime();
    Queue::fake([ProcessPixelforgeWebhook::class]);
    $body = json_encode(['id' => 'evt_ping', 'type' => 'ping']);
    $timestamp = now()->timestamp + match ($mode) {
        'old' => -301, 'future' => 301, default => 0
    };
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $mode === 'invalid' ? 'wrong' : 'whsec_test');

    $this->call('POST', route('webhooks.pixelforge'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}"], content: $body)->assertBadRequest();

    $this->assertDatabaseCount('webhook_events', 0);
    Queue::assertNothingPushed();
})->with(['old', 'future', 'invalid']);

test('duplicate signed deliveries are stored and settled only once even before create returns', function () {
    Queue::fake([ProcessPixelforgeWebhook::class]);
    $generation = Generation::factory()->create();
    $payload = ['id' => 'evt_success', 'type' => 'generation.succeeded', 'data' => ['object' => pixelforgeSnapshot($generation)]];
    $body = json_encode($payload);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    for ($i = 0; $i < 2; $i++) {
        $this->call('POST', route('webhooks.pixelforge'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}"], content: $body)->assertNoContent();
    }
    $event = WebhookEvent::query()->sole();
    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));
    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseCount('webhook_events', 1);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded']);
    $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'processing_status' => 'processed']);
    Queue::assertPushed(ProcessPixelforgeWebhook::class, fn ($job) => $job->eventId === $event->id);
});

test('an event remains recoverable when its generation is not yet matched', function () {
    $generation = Generation::factory()->make();
    $event = WebhookEvent::factory()->create([
        'event_type' => 'generation.succeeded', 'provider_generation_id' => 'gen_test',
        'payload' => ['data' => ['object' => pixelforgeSnapshot($generation)]],
    ]);

    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'processing_status' => 'pending', 'processed_at' => null, 'attempts' => 1]);
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('signed ping events are acknowledged without creating financial records', function () {
    $event = WebhookEvent::factory()->create();

    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'processing_status' => 'processed']);
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('out of order events are recorded while the terminal result stays unchanged', function () {
    $generation = Generation::factory()->accepted()->create();
    foreach (['succeeded', 'processing'] as $status) {
        $event = WebhookEvent::factory()->create([
            'event_type' => 'generation.'.$status,
            'provider_generation_id' => $generation->provider_generation_id,
            'payload' => ['data' => ['object' => pixelforgeSnapshot($generation, $status)]],
        ]);
        (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));
    }

    $this->assertDatabaseCount('webhook_events', 2);
    $this->assertDatabaseMissing('webhook_events', ['processing_status' => 'pending']);
    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded']);
});

test('invalid successful events remain unprocessed without leaving a partial settlement', function () {
    $generation = Generation::factory()->accepted()->create();
    $snapshot = pixelforgeSnapshot($generation);
    unset($snapshot['usage']['reasoning_tokens']);
    $event = WebhookEvent::factory()->create([
        'event_type' => 'generation.succeeded',
        'provider_generation_id' => $generation->provider_generation_id,
        'payload' => ['data' => ['object' => $snapshot]],
    ]);

    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'processing_status' => 'pending', 'processed_at' => null]);
    $this->assertDatabaseCount('generation_settlements', 0);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued']);
});

test('the first valid delivery immediately queues processing without waiting for recovery', function () {
    Queue::fake([ProcessPixelforgeWebhook::class]);
    $body = json_encode(['id' => 'evt_first_delivery', 'type' => 'ping']);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    $this->call('POST', route('webhooks.pixelforge'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}"], content: $body)->assertNoContent();

    $event = WebhookEvent::query()->sole();
    Queue::assertPushed(ProcessPixelforgeWebhook::class, fn ($job) => $job->eventId === $event->id);
    $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'processing_status' => 'pending']);
});

test('unknown signed events are recorded once and acknowledged without queueing work', function () {
    Queue::fake([ProcessPixelforgeWebhook::class]);
    $this->freezeTime();
    $body = json_encode(['id' => 'evt_future_event', 'type' => 'generation.archived', 'data' => ['object' => ['id' => 'gen_future']]]);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    for ($delivery = 0; $delivery < 2; $delivery++) {
        $this->call('POST', route('webhooks.pixelforge'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}"], content: $body)->assertNoContent();
    }

    $this->assertDatabaseCount('webhook_events', 1);
    $this->assertDatabaseHas('webhook_events', ['provider_event_id' => 'evt_future_event', 'event_type' => 'generation.archived', 'processing_status' => 'ignored', 'next_attempt_at' => null]);
    expect(WebhookEvent::query()->sole()->payload)->toBe(json_decode($body, true));
    $this->assertDatabaseCount('generation_settlements', 0);
    Queue::assertNothingPushed();
});

test('malformed signed events return JSON 422 without an Accept header', function (array $payload, string $field) {
    Queue::fake([ProcessPixelforgeWebhook::class]);
    $body = json_encode($payload);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    $this->call('POST', route('webhooks.pixelforge'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}"], content: $body)
        ->assertUnprocessable()->assertJsonValidationErrors($field)->assertHeader('Content-Type', 'application/json');

    $this->assertDatabaseCount('webhook_events', 0);
    Queue::assertNothingPushed();
})->with([
    'known event missing generation' => [['id' => 'evt_incomplete', 'type' => 'generation.succeeded'], 'data.object'],
    'unknown event missing identity' => [['type' => 'generation.archived'], 'id'],
]);

test('webhook deliveries do not create sessions or issue cookies', function () {
    config(['session.driver' => 'database']);
    Queue::fake([ProcessPixelforgeWebhook::class]);
    $body = json_encode(['id' => 'evt_stateless', 'type' => 'ping']);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    $response = $this->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'Pixelforge-Webhooks/1.0',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $body);

    $response->assertNoContent();
    expect($response->headers->getCookies())->toBe([]);
    $this->assertDatabaseCount('sessions', 0);
    $this->assertDatabaseHas('webhook_events', ['provider_event_id' => 'evt_stateless']);
    Queue::assertPushed(ProcessPixelforgeWebhook::class);
});

test('high precision usage settles once and is returned to the owner as a string', function () {
    $generation = Generation::factory()->accepted()->create();
    $snapshot = pixelforgeSnapshot($generation);
    $snapshot['rates'] = array_fill_keys(GenerationPricing::TOKEN_TYPES, '0.000000001');
    $snapshot['usage'] = ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'total_tokens' => 1];
    $event = WebhookEvent::factory()->create([
        'event_type' => 'generation.succeeded', 'provider_generation_id' => $generation->provider_generation_id,
        'payload' => ['data' => ['object' => $snapshot]],
    ]);

    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));
    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationSnapshot::class));

    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'provider_cost' => '0.000000000000001000', 'user_charge' => '0.000000000000001250']);
    $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'processing_status' => 'processed']);
    expect((new GenerationResource($generation->refresh()->load('settlement')))->resolve()['actual_cost'])->toBe('0.000000000000001250');
});
