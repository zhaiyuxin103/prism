<?php

use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the initial schema preserves exact dollar strings and settlement constraints', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create([
        'estimated_provider_cost' => '0.000000000000001000',
        'estimated_user_cost' => '0.000000000000001250',
        'capacity_wait_started_at' => now(),
    ]);
    $settlement = GenerationSettlement::factory()->for($generation)->create([
        'provider_cost' => '0.000000000000001000',
        'user_charge' => '0.000000000000001250',
    ]);

    expect($generation->refresh()->getRawOriginal('estimated_provider_cost'))->toBe('0.000000000000001000');
    expect($generation->getRawOriginal('estimated_user_cost'))->toBe('0.000000000000001250');
    expect($generation->capacity_wait_started_at->getTimestamp())->toBe(now()->getTimestamp());
    expect($settlement->refresh()->getRawOriginal('provider_cost'))->toBe('0.000000000000001000');
    expect($settlement->getRawOriginal('user_charge'))->toBe('0.000000000000001250');

    expect(fn () => DB::transaction(fn () => $generation->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => GenerationSettlement::factory()->for($generation)->create()))->toThrow(QueryException::class);
});

test('generation migrations can roll back populated tables and rebuild the current schema', function () {
    GenerationSettlement::factory()->create();
    WebhookEvent::factory()->create();
    $migrations = [
        require database_path('migrations/2026_11_16_184328_create_generations_table.php'),
        require database_path('migrations/2026_11_16_184329_create_generation_settlements_table.php'),
        require database_path('migrations/2026_11_16_184330_create_webhook_events_table.php'),
    ];

    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }
    foreach ($migrations as $migration) {
        $migration->up();
    }

    $this->assertDatabaseCount('generations', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
    $this->assertDatabaseCount('webhook_events', 0);
    $generation = Generation::factory()->create(['capacity_wait_started_at' => now()]);
    $settlement = GenerationSettlement::factory()->for($generation)->make([
        'provider_cost' => '0.000000000000001000',
        'user_charge' => '0.000000000000001250',
    ]);
    unset($settlement->calculation_version);
    $settlement->save();

    expect($settlement->refresh()->calculation_version)->toBe('v2');
    expect($settlement->getRawOriginal('user_charge'))->toBe('0.000000000000001250');
});
