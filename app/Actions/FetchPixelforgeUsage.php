<?php

namespace App\Actions;

use App\Exceptions\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

/**
 * @phpstan-type UsageDay array{date: string, generations: int, cost_usd: numeric-string}
 * @phpstan-type UsageReport array{days: array<string, UsageDay>, total: array{generations: int, cost_usd: numeric-string}, checked_at: string}
 */
class FetchPixelforgeUsage
{
    public function __construct(private PixelforgeClient $client) {}

    /** @return UsageReport */
    public function handle(string $startDate, string $endDate): array
    {
        $payload = $this->client->usage($startDate, $endDate);
        $money = ['required', 'string', 'regex:/^\d{1,30}\.\d{9}$/D'];
        $validator = Validator::make($payload, [
            'object' => ['required', 'in:usage'],
            'start_date' => ['required', 'in:'.$startDate],
            'end_date' => ['required', 'in:'.$endDate],
            'data' => ['required', 'array', 'min:1', 'max:92'],
            'data.*.date' => ['required', 'date_format:Y-m-d', 'distinct:strict'],
            'data.*.generations' => ['required', 'integer', 'min:0'],
            'data.*.cost_usd' => $money,
            'total.generations' => ['required', 'integer', 'min:0'],
            'total.cost_usd' => $money,
        ]);
        throw_if($validator->fails(), PixelforgeException::class, 'invalid_usage', 'The provider usage response could not be verified.');

        $days = [];
        $cost = GenerationPricing::ZERO;
        $count = 0;
        foreach ($payload['data'] as $day) {
            $date = $day['date'];
            throw_if($date < $startDate || $date > $endDate, PixelforgeException::class, 'invalid_usage', 'The provider returned a different date range.');
            $days[$date] = ['date' => $date, 'generations' => (int) $day['generations'], 'cost_usd' => $day['cost_usd']];
            $cost = bcadd($cost, $day['cost_usd'], GenerationPricing::PRECISION);
            $count += (int) $day['generations'];
        }

        $expectedDays = (int) CarbonImmutable::parse($startDate, 'UTC')->diffInDays(CarbonImmutable::parse($endDate, 'UTC')) + 1;
        throw_if(count($days) !== $expectedDays || $count !== (int) $payload['total']['generations'] || bccomp($cost, $payload['total']['cost_usd'], GenerationPricing::PRECISION) !== 0, PixelforgeException::class, 'invalid_usage', 'The provider usage response is incomplete or inconsistent.');

        return [
            'days' => $days,
            'total' => ['generations' => $count, 'cost_usd' => $cost],
            'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }
}
