<?php

namespace App\Http\Controllers;

use App\Actions\DemoLogin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Spatie\LoginLink\Http\Controllers\LoginLinkController;
use Spatie\LoginLink\Http\Requests\LoginLinkRequest;

class DemoLoginController extends LoginLinkController
{
    public function __construct(private DemoLogin $demoLogin) {}

    public function __invoke(LoginLinkRequest $request): RedirectResponse
    {
        abort_unless($this->demoLogin->allowed($request), 404);

        $input = $request->validate([
            'account' => ['required', 'string', Rule::in(array_keys(DemoLogin::ACCOUNTS))],
        ]);

        $user = User::query()->where('is_demo', true)
            ->where('email', DemoLogin::ACCOUNTS[$input['account']]['email'])->firstOrFail();
        $team = $user->currentTeam ?? $user->personalTeam();
        abort_if($team === null, 404);

        /** Only server-selected identities and redirects may reach the package. */
        $request->query->replace([]);
        $request->replace([
            'key' => $user->getKey(),
            'guard' => 'web',
            'redirect_url' => route('dashboard', ['current_team' => $team->slug]),
        ]);
        $request->session()->invalidate();

        $response = parent::__invoke($request);
        $request->session()->regenerate();

        return $response;
    }
}
