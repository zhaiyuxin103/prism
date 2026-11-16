<?php

namespace App\Http\Controllers;

use App\Actions\GenerationPricing;
use App\Actions\PixelforgeClient;
use App\Http\Requests\GenerationInputRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

class GenerationEstimateController extends Controller
{
    public function __invoke(GenerationInputRequest $request, PixelforgeClient $client, GenerationPricing $pricing): JsonResponse
    {
        $input = $request->generationInput();
        $estimate = $pricing->estimate($input, $client->pricing());
        $expires = now()->addMinutes(5);

        return response()->json([
            'estimated_cost' => $estimate['user_charge'],
            'maximum_cost' => $estimate['maximum_user_cost'],
            'expires_at' => $expires->toIso8601String(),
            'quote' => Crypt::encryptString(json_encode([
                'user_id' => $request->user()->id,
                'request_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)),
                'estimate' => $estimate,
                'expires_at' => $expires->timestamp,
            ], JSON_THROW_ON_ERROR)),
        ]);
    }
}
