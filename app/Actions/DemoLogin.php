<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Http\Request;

class DemoLogin
{
    public const array ACCOUNTS = [
        'admin' => [
            'name' => 'Demo Admin', 'email' => 'admin@demo.prism.test', 'balance' => '25', 'is_admin' => true,
            'label' => 'Administrator', 'description' => 'Explore generation, financial reports and reconciliation.',
        ],
        'user' => [
            'name' => 'Demo User', 'email' => 'user@demo.prism.test', 'balance' => '10', 'is_admin' => false,
            'label' => 'Regular user', 'description' => 'Generate images and review credit history.',
        ],
        'low-balance' => [
            'name' => 'Demo Low Balance', 'email' => 'low-balance@demo.prism.test', 'balance' => '0.001', 'is_admin' => false,
            'label' => 'Low balance', 'description' => 'Try generating with insufficient credits, then top up and retry.',
        ],
        'new-user' => [
            'name' => 'Demo New User', 'email' => 'new-user@demo.prism.test', 'balance' => '0', 'is_admin' => false,
            'label' => 'New user', 'description' => 'Explore an empty workspace and make a first credit purchase.',
        ],
    ];

    public function enabled(): bool
    {
        return config('demo.enabled') && app()->environment(config('login-link.allowed_environments'));
    }

    public function allowed(Request $request): bool
    {
        return $this->enabled() && in_array($request->getHost(), config('login-link.allowed_hosts'), true);
    }

    /** @return list<array{account: string, label: string, description: string, available: numeric-string}> */
    public function accounts(Request $request): array
    {
        if (! $this->allowed($request)) {
            return [];
        }

        $users = User::query()->where('is_demo', true)
            ->whereIn('email', array_column(self::ACCOUNTS, 'email'))
            ->with('creditAccount')->get()->keyBy('email');
        $accounts = [];

        foreach (self::ACCOUNTS as $key => $profile) {
            $user = $users->get($profile['email']);
            if ($user === null) {
                continue;
            }

            $accounts[] = [
                'account' => $key,
                'label' => $profile['label'],
                'description' => $profile['description'],
                'available' => $user->creditAccount?->available() ?? GenerationPricing::ZERO,
            ];
        }

        return $accounts;
    }
}
