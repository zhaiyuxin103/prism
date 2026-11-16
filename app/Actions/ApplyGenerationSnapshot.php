<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class ApplyGenerationSnapshot
{
    public function __construct(private GenerationPricing $pricing, private CreditLedger $credits) {}

    /** @param array<string, mixed> $snapshot */
    public function handle(string $generationId, array $snapshot): void
    {
        $data = Validator::make($snapshot, [
            'id' => ['required', 'string', 'starts_with:gen_', 'max:255'],
            'status' => ['required', Rule::enum(GenerationStatus::class)],
            'metadata.local_generation_id' => ['required', 'uuid'],
            'prompt' => ['required', 'string'],
            'size' => ['required', 'string'],
            'quality' => ['required', 'string'],
            'reference_images' => ['present', 'array'],
            'reference_images.*' => ['string'],
            'rates' => ['required', 'array'],
            'usage' => ['required_if:status,succeeded', 'nullable', 'array'],
            'image_url' => ['required_if:status,succeeded', 'nullable', 'url:http,https'],
            'image_url_expires_at' => ['required_if:status,succeeded', 'nullable', 'date'],
            'created_at' => ['required', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['required_if:status,succeeded,failed', 'nullable', 'date'],
            'error' => ['nullable', 'array'],
            'error.code' => ['nullable', 'string', 'max:255'],
            'error.message' => ['nullable', 'string'],
        ])->validate();

        DB::transaction(function () use ($generationId, $data): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generationId);
            $status = GenerationStatus::from($data['status']);

            throw_if($data['metadata']['local_generation_id'] !== $generation->id ||
                ($generation->provider_generation_id !== null && $generation->provider_generation_id !== $data['id']), RuntimeException::class, 'Provider generation identity mismatch.');

            foreach (['prompt', 'size', 'quality', 'reference_images'] as $field) {
                throw_if($data[$field] !== $generation->request_payload[$field], RuntimeException::class, 'Provider request snapshot mismatch.');
            }

            if ($generation->status->isTerminal()) {
                throw_if($status->isTerminal() && $status !== $generation->status, RuntimeException::class, 'Conflicting terminal generation status.');

                return;
            }

            if ($generation->status === GenerationStatus::Processing && $status === GenerationStatus::Queued) {
                return;
            }

            $generation->fill([
                'provider_generation_id' => $data['id'],
                'submission_state' => SubmissionState::Accepted,
                'status' => $status,
                'provider_rates' => $data['rates'],
                'provider_created_at' => $data['created_at'],
                'provider_started_at' => $data['started_at'] ?? $generation->provider_started_at,
                'provider_completed_at' => $data['completed_at'] ?? null,
                'last_checked_at' => now(),
                'capacity_wait_started_at' => null,
                'error_code' => null,
                'error_message' => null,
                'next_attempt_at' => $status->isTerminal() ? null : now()->addSeconds(45),
            ]);

            if ($status->isTerminal()) {
                $amounts = $status === GenerationStatus::Succeeded
                    ? $this->pricing->calculate($data['usage'], $data['rates'])
                    : ['provider_cost' => GenerationPricing::ZERO, 'user_charge' => GenerationPricing::ZERO];

                GenerationSettlement::query()->create([
                    'generation_id' => $generation->id,
                    'outcome' => $status->value,
                    ...$amounts,
                    'calculation_version' => 'v2',
                    'settled_at' => now(),
                ]);

                $this->credits->settle($generation, $amounts['user_charge']);

                $generation->fill([
                    'usage' => $status === GenerationStatus::Succeeded ? $data['usage'] : null,
                    'image_url' => $data['image_url'] ?? null,
                    'image_url_expires_at' => $data['image_url_expires_at'] ?? null,
                    'error_code' => $data['error']['code'] ?? null,
                    'error_message' => $data['error']['message'] ?? null,
                ]);
            }

            $generation->save();
        }, 3);
    }

    public function reject(string $id, string $code, string $message): void
    {
        DB::transaction(function () use ($id, $code, $message): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($id);

            if ($generation->status->isTerminal() || $generation->submission_state === SubmissionState::Accepted) {
                return;
            }

            $generation->update([
                'submission_state' => SubmissionState::Rejected,
                'status' => GenerationStatus::Failed,
                'error_code' => $code,
                'error_message' => $message,
                'next_attempt_at' => null,
            ]);
            GenerationSettlement::query()->create([
                'generation_id' => $id,
                'outcome' => 'failed',
                'provider_cost' => GenerationPricing::ZERO,
                'user_charge' => GenerationPricing::ZERO,
                'calculation_version' => 'v2',
                'settled_at' => now(),
            ]);
            $this->credits->settle($generation, GenerationPricing::ZERO);
        }, 3);
    }
}
