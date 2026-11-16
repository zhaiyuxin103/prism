<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Enums\SubmissionState;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GenerationController extends Controller
{
    public function store(StoreGenerationRequest $request, CreateGeneration $create): JsonResponse
    {
        $generation = $create->handle($request->user(), $request->generationInput(), $request->validated('client_request_id'), $request->validated('quote'));
        SyncGeneration::dispatch($generation->id)->afterCommit();

        return (new GenerationResource($generation->load('settlement')))->response()->setStatusCode(202);
    }

    public function resume(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($generation): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);
            if (! $generation->status->isTerminal() && $generation->next_attempt_at === null) {
                $generation->update([
                    'submit_attempts' => 0,
                    'poll_attempts' => 0,
                    'capacity_wait_started_at' => null,
                    'next_attempt_at' => now(),
                    'error_message' => $generation->submission_state === SubmissionState::Uncertain
                        ? 'Checking the original submission. No new generation will be created.' : null,
                ]);
            }
        }, 3);
        SyncGeneration::dispatch($generation->id)->afterCommit();

        return (new GenerationResource($generation->refresh()->load('settlement')))->response()->setStatusCode(202);
    }
}
