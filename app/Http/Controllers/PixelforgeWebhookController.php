<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPixelforgeWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.pixelforge.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503);
        $body = $request->getContent();
        abort_if(strlen($body) > 131072, 413);
        $header = (string) $request->header('Pixelforge-Signature');
        abort_unless(preg_match('/^t=(\d{1,12}),v1=([a-f0-9]{64})$/D', $header, $signature) === 1, 400, 'Invalid signature.');
        abort_if(abs(now()->getTimestamp() - (int) $signature[1]) > 300, 400, 'Expired signature.');
        abort_unless(hash_equals(hash_hmac('sha256', $signature[1].'.'.$body, $secret), $signature[2]), 400, 'Invalid signature.');

        $envelope = $request->validate([
            'id' => ['required', 'string', 'starts_with:evt_', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ]);
        $supported = in_array($envelope['type'], ['ping', 'generation.processing', 'generation.succeeded', 'generation.failed'], true);
        $data = $supported ? $request->validate([
            'data.object' => ['required_unless:type,ping', 'array'],
            'data.object.id' => ['required_unless:type,ping', 'string', 'starts_with:gen_', 'max:255'],
        ]) : [];

        $event = WebhookEvent::query()->firstOrCreate(['provider_event_id' => $envelope['id']], [
            'event_type' => $envelope['type'],
            'provider_generation_id' => $data['data']['object']['id'] ?? null,
            'payload' => $request->json()->all(),
            'received_at' => now(),
            'processing_status' => $supported ? 'pending' : 'ignored',
            'processed_at' => $supported ? null : now(),
            'next_attempt_at' => $supported ? now() : null,
        ]);

        if ($event->processing_status === 'pending') {
            ProcessPixelforgeWebhook::dispatch($event->id)->afterCommit();
        }

        return response()->noContent();
    }
}
