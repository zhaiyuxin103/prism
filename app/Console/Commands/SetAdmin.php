<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\search;

#[Description('Grant or revoke platform administrator access by user ID, email, or interactive search')]
#[Signature('users:admin {user? : Existing user ID or email; omit to search and select} {--revoke : Remove administrator access}')]
class SetAdmin extends Command
{
    public function handle(): int
    {
        $identifier = trim((string) $this->argument('user'));
        if ($identifier === '') {
            if (! $this->input->isInteractive()) {
                $this->error('Pass a user ID or email when using --no-interaction.');

                return self::INVALID;
            }

            $users = $this->searchUsers('');
            if ($users->isEmpty()) {
                $this->error('No users exist. Ask the user to register first.');

                return self::FAILURE;
            }

            $this->info('Recent users (up to 20). Search to find other accounts.');
            $this->showUsers($users);
            $identifier = (string) search(
                label: $this->option('revoke') ? 'Select a user to revoke administrator access' : 'Select a user to grant administrator access',
                options: fn (string $value): array => ['cancel' => 'Cancel without changes'] + $this->searchUsers($value)
                    ->mapWithKeys(fn (User $user): array => [$user->id => "#{$user->id} | {$user->name} | {$user->email} | ".($user->is_admin ? 'admin' : 'user')])->all(),
                placeholder: 'Name, email or exact ID',
                scroll: 10,
                hint: 'Type to search, use arrow keys to select, then Enter to apply. Ctrl+C cancels.',
            );
            if ($identifier === 'cancel') {
                $this->info('Cancelled. No permissions changed.');

                return self::SUCCESS;
            }
        }

        $matches = ctype_digit($identifier)
            ? User::query()->whereKey($this->validId($identifier))->get()
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->limit(2)->get();
        if ($matches->count() !== 1) {
            $this->error($matches->isEmpty() ? 'No user found with that ID or email.' : 'Multiple users match that email. Pass the exact user ID instead.');

            return self::FAILURE;
        }

        $user = $matches->first();
        $isAdmin = ! $this->option('revoke');
        if ($user->is_admin === $isAdmin) {
            $this->info($isAdmin ? 'This user is already an administrator.' : 'This user is not an administrator.');

            return self::SUCCESS;
        }

        $user->is_admin = $isAdmin;
        $user->save();
        $this->showUsers($matches);
        $this->info($isAdmin ? 'Administrator access granted. Email verification is still required.' : 'Administrator access revoked.');

        return self::SUCCESS;
    }

    /** @return Collection<int, User> */
    private function searchUsers(string $term): Collection
    {
        $term = trim($term);

        return User::query()->when($term !== '', fn ($query) => $query->where(function ($query) use ($term): void {
            $query->whereLike('name', '%'.$term.'%')->orWhereLike('email', '%'.$term.'%');
            if (ctype_digit($term)) {
                $query->orWhere('id', $this->validId($term));
            }
        }))->orderByDesc('id')->limit(20)->get();
    }

    private function validId(string $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    }

    /** @param Collection<int, User> $users */
    private function showUsers(Collection $users): void
    {
        $this->table(['ID', 'Name', 'Email', 'Admin', 'Verified'], $users->map(fn (User $user): array => [
            $user->id, $user->name, $user->email, $user->is_admin ? 'Yes' : 'No', $user->hasVerifiedEmail() ? 'Yes' : 'No',
        ])->all());
    }
}
