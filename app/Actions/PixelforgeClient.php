<?php

namespace App\Actions;

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PixelforgeClient
{
    public function configured(): bool
    {
        return filled(config('services.pixelforge.api_key'));
    }

    /** @return array<string, mixed> */
    public function pricing(): array
    {
        return Cache::remember('pixelforge:pricing:'.hash('sha256', config('services.pixelforge.base_url')), 60, function (): array {
            try {
                return $this->decode($this->request()->get('/pricing'));
            } catch (PixelforgeException $exception) {
                Log::warning('Pixelforge pricing request failed.', ['code' => $exception->errorCode]);
                throw new PixelforgeException('invalid_pricing', 'Image pricing is temporarily unavailable. Please try again.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function submit(Generation $generation): array
    {
        return $this->decode($this->authenticated()
            ->withHeaders(['Idempotency-Key' => $generation->id])
            ->post('/generations', $generation->request_payload));
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->decode($this->authenticated()->get('/generations/'.rawurlencode($id)));
    }

    /** @return array<string, mixed> */
    public function usage(string $startDate, string $endDate): array
    {
        $cooldownKey = 'pixelforge:usage-cooldown:'.hash('sha256', config('services.pixelforge.base_url').'|'.config('services.pixelforge.api_key'));
        $retryAt = (int) Cache::get($cooldownKey, 0);
        if ($retryAt > now()->getTimestamp()) {
            throw new PixelforgeException('rate_limit_exceeded', 'Usage is temporarily unavailable.', true, true, $retryAt - now()->getTimestamp());
        }

        try {
            return $this->decode($this->authenticated()->get('/usage', [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]));
        } catch (PixelforgeException $exception) {
            if ($exception->retryAfter > 0) {
                Cache::put($cooldownKey, now()->getTimestamp() + $exception->retryAfter, $exception->retryAfter);
            }

            throw $exception;
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.pixelforge.base_url'), '/'))
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->withoutRedirecting();
    }

    private function authenticated(): PendingRequest
    {
        throw_unless($this->configured(), PixelforgeException::class, 'not_configured', 'Image generation is temporarily unavailable.');

        $lock = Cache::lock('pixelforge:request-budget-lock', 5);
        throw_unless($lock->get(), PixelforgeException::class, 'local_rate_limit', 'Waiting for available capacity.', true, true, 5);

        try {
            if (RateLimiter::tooManyAttempts('pixelforge:requests', (int) config('services.pixelforge.request_budget'))) {
                throw new PixelforgeException('local_rate_limit', 'Waiting for available capacity.', true, true, max(1, RateLimiter::availableIn('pixelforge:requests')));
            }
            RateLimiter::hit('pixelforge:requests', 60);
        } finally {
            $lock->release();
        }

        return $this->request()->withToken(config('services.pixelforge.api_key'));
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if ($response->successful() && is_array($response->json())) {
            return $response->json();
        }

        $code = (string) $response->json('error.code', 'provider_unavailable');
        $retryable = in_array($code, ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'], true);
        $definitive = in_array($code, ['validation_failed', 'content_policy_violation', 'invalid_api_key', 'daily_limit_exceeded', 'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'], true);
        $message = match ($code) {
            'content_policy_violation' => 'This prompt was rejected by the provider. Please try a different prompt.',
            'validation_failed' => 'The provider could not accept these image settings.',
            'daily_limit_exceeded' => 'The daily generation limit has been reached. Please try again tomorrow.',
            'invalid_api_key' => 'Image generation is temporarily unavailable. Please contact the operator.',
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded' => 'The provider is busy. We will retry shortly.',
            default => 'We could not confirm the provider response. Your request is being checked.',
        };

        throw new PixelforgeException($code, $message, $retryable || ! $definitive, $definitive, max(0, (int) $response->header('Retry-After')));
    }
}
