<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OperationalMesa
{
    public static function now(): Carbon
    {
        return now()->timezone(config('app.timezone'));
    }

    public static function dateLabel(?Carbon $now = null): string
    {
        return ($now ?? self::now())->isoFormat('dddd D [de] MMMM');
    }

    public static function monthlyGrowth(Builder $query, string $column = 'created_at', ?Carbon $now = null): array
    {
        $now ??= self::now();
        $start = $now->copy()->startOfMonth()->subMonths(11);
        $prior = (clone $query)->where($column, '<', $start)->count();
        $dates = (clone $query)->whereNotNull($column)->where($column, '>=', $start)->orderBy($column)->pluck($column);

        $buckets = [];
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->copy()->addMonths($offset);
            $buckets[$month->format('Y-m')] = [
                'label' => $month->translatedFormat('M'),
                'added' => 0,
            ];
        }

        foreach ($dates as $date) {
            $key = Carbon::parse($date)->timezone(config('app.timezone'))->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['added']++;
            }
        }

        return self::runningPoints($buckets, $prior);
    }

    public static function runningPoints(array $buckets, int $prior = 0): array
    {
        $running = $prior;
        $points = [];
        foreach ($buckets as $bucket) {
            $running += (int) $bucket['added'];
            $points[] = [
                'label' => $bucket['label'],
                'added' => (int) $bucket['added'],
                'total' => $running,
            ];
        }

        $latest = $points[count($points) - 1] ?? ['added' => 0, 'total' => 0];
        $previous = $points[count($points) - 2] ?? ['total' => 0];

        return [
            'points' => $points,
            'added' => $latest['added'] ?? 0,
            'delta' => ($latest['total'] ?? 0) - ($previous['total'] ?? 0),
        ];
    }
}
