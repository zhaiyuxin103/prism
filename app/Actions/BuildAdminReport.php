<?php

namespace App\Actions;

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type Amounts array{generations: int, failed: int, user_charge: numeric-string, provider_cost: numeric-string, profit: numeric-string}
 * @phpstan-type Comparison array{status: string, provider_cost: string, provider_generations: int, cost_difference: string, generation_difference: int}
 * @phpstan-type DailyRow array{date: string, amounts: Amounts, reconciliation: Comparison|null}
 * @phpstan-type UserRow array{id: int|null, name: string, email: string|null, amounts: Amounts, days: list<array{date: string, amounts: Amounts}>}
 * @phpstan-type Report array{totals: Amounts, days: list<DailyRow>, users: list<UserRow>, reconciliation: array{status: string, message: string|null, checked_at: string|null, total: Comparison|null}, unresolved_generations: int, generated_at: string}
 */
class BuildAdminReport
{
    public function __construct(private FetchPixelforgeUsage $usage) {}

    /** @return Report */
    public function handle(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate, 'UTC')->startOfDay();
        $end = CarbonImmutable::parse($endDate, 'UTC')->addDay()->startOfDay();
        $days = [];
        for ($date = $start; $date < $end; $date = $date->addDay()) {
            $days[$date->toDateString()] = ['date' => $date->toDateString(), 'amounts' => $this->zero(), 'reconciliation' => null];
        }

        $totals = $this->zero();
        $users = [];
        $settlements = GenerationSettlement::query()
            ->join('generations', 'generations.id', '=', 'generation_settlements.generation_id')
            ->leftJoin('users', 'users.id', '=', 'generations.user_id')
            ->where('generations.provider_completed_at', '>=', $start)
            ->where('generations.provider_completed_at', '<', $end)
            ->select([
                'generation_settlements.provider_cost', 'generation_settlements.user_charge', 'generation_settlements.outcome',
                'generations.provider_completed_at', 'generations.user_id', 'users.name', 'users.email',
            ])
            ->orderBy('generation_settlements.id')->toBase()->cursor();

        foreach ($settlements as $settlement) {
            $date = CarbonImmutable::parse($settlement->provider_completed_at, 'UTC')->toDateString();
            $userId = $settlement->user_id === null ? null : (int) $settlement->user_id;
            $userKey = $userId ?? 'deleted';
            $users[$userKey] ??= [
                'id' => $userId, 'name' => $settlement->name ?? 'Deleted users', 'email' => $settlement->email,
                'amounts' => $this->zero(), 'days' => [],
            ];
            $amounts = [
                'generations' => $settlement->outcome === 'succeeded' ? 1 : 0,
                'failed' => $settlement->outcome === 'failed' ? 1 : 0,
                'user_charge' => $settlement->user_charge,
                'provider_cost' => $settlement->provider_cost,
                'profit' => bcsub($settlement->user_charge, $settlement->provider_cost, GenerationPricing::PRECISION),
            ];
            $totals = $this->add($totals, $amounts);
            $days[$date] = ['date' => $date, 'amounts' => $this->add($days[$date]['amounts'], $amounts), 'reconciliation' => null];
            $users[$userKey]['amounts'] = $this->add($users[$userKey]['amounts'], $amounts);
            $users[$userKey]['days'][$date] = ['date' => $date, 'amounts' => $this->add($users[$userKey]['days'][$date]['amounts'] ?? $this->zero(), $amounts)];
        }

        foreach ($users as &$user) {
            krsort($user['days']);
            $user['days'] = array_values($user['days']);
        }
        unset($user);
        $users = array_values($users);
        usort($users, fn (array $a, array $b): int => bccomp($b['amounts']['user_charge'], $a['amounts']['user_charge'], GenerationPricing::PRECISION) ?: (($a['id'] ?? 0) <=> ($b['id'] ?? 0)));

        $reconciliation = ['status' => 'unavailable', 'message' => null, 'checked_at' => null, 'total' => null];
        try {
            $usage = $this->usage->handle($startDate, $endDate);
            $matched = true;
            foreach ($days as $date => &$day) {
                $day['reconciliation'] = $this->compare($day['amounts'], $usage['days'][$date]);
                $matched = $matched && $day['reconciliation']['status'] === 'matched';
            }
            unset($day);
            $reconciliation = [
                'status' => $matched ? 'matched' : 'mismatch',
                'message' => null,
                'checked_at' => $usage['checked_at'],
                'total' => $this->compare($totals, $usage['total']),
            ];
        } catch (PixelforgeException|ConnectionException $exception) {
            $code = $exception instanceof PixelforgeException ? $exception->errorCode : 'connection_failed';
            Log::warning('Admin report usage reconciliation unavailable.', ['code' => $code, 'start_date' => $startDate, 'end_date' => $endDate]);
            $reconciliation['message'] = match ($code) {
                'not_configured' => 'Configure the Pixelforge API key to reconcile provider costs.',
                'invalid_usage' => 'Pixelforge returned incomplete or inconsistent usage data. Try refreshing the report later.',
                'local_rate_limit', 'rate_limit_exceeded', 'concurrency_limit_exceeded' => 'The provider request limit has been reached. Wait before refreshing the report.',
                default => 'Provider usage is unavailable. Local figures are shown; reconciliation has not completed.',
            };
        }

        return [
            'totals' => $totals,
            'days' => array_values(array_reverse($days, true)),
            'users' => $users,
            'reconciliation' => $reconciliation,
            'unresolved_generations' => Generation::query()->whereIn('status', ['queued', 'processing'])->count(),
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    /** @return Amounts */
    private function zero(): array
    {
        return ['generations' => 0, 'failed' => 0, 'user_charge' => GenerationPricing::ZERO, 'provider_cost' => GenerationPricing::ZERO, 'profit' => GenerationPricing::ZERO];
    }

    /**
     * @param  Amounts  $left
     * @param  Amounts  $right
     * @return Amounts
     */
    private function add(array $left, array $right): array
    {
        return [
            'generations' => $left['generations'] + $right['generations'],
            'failed' => $left['failed'] + $right['failed'],
            'user_charge' => bcadd($left['user_charge'], $right['user_charge'], GenerationPricing::PRECISION),
            'provider_cost' => bcadd($left['provider_cost'], $right['provider_cost'], GenerationPricing::PRECISION),
            'profit' => bcadd($left['profit'], $right['profit'], GenerationPricing::PRECISION),
        ];
    }

    /**
     * @param  Amounts  $local
     * @param  array{generations: int, cost_usd: numeric-string}  $provider
     * @return Comparison
     */
    private function compare(array $local, array $provider): array
    {
        $difference = bcsub($local['provider_cost'], $provider['cost_usd'], GenerationPricing::PRECISION);
        $countDifference = $local['generations'] - $provider['generations'];

        return [
            'status' => bccomp($difference, '0', GenerationPricing::PRECISION) === 0 && $countDifference === 0 ? 'matched' : 'mismatch',
            'provider_cost' => $provider['cost_usd'],
            'provider_generations' => $provider['generations'],
            'cost_difference' => $difference,
            'generation_difference' => $countDifference,
        ];
    }
}
