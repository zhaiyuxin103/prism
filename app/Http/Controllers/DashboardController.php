<?php

namespace App\Http\Controllers;

use App\Actions\PixelforgeClient;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'generations' => fn () => GenerationResource::collection(
                Generation::query()->where('user_id', $request->user()->id)
                    ->with('settlement')->latest()->orderByDesc('id')->paginate(12),
            ),
            'hasActiveGenerations' => fn () => Generation::query()->where('user_id', $request->user()->id)
                ->whereIn('status', ['queued', 'processing'])->whereNotNull('next_attempt_at')->exists(),
            'hasPausedGenerations' => fn () => Generation::query()->where('user_id', $request->user()->id)
                ->whereIn('status', ['queued', 'processing'])->whereNull('next_attempt_at')->exists(),
            'providerConfigured' => app(PixelforgeClient::class)->configured(),
        ]);
    }
}
