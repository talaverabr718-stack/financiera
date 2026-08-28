<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class CollectionRouteStop extends Model
{
    protected $fillable = ['collection_route_id', 'client_id', 'position', 'status', 'visited_at', 'notes'];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }

    public function visitedAtLabel(): ?string
    {
        return $this->visited_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    public function paidAtLabel(): ?string
    {
        $record = $this->relationLoaded('records')
            ? $this->records->first(fn (CollectionRecord $record) => $record->outcome === 'collected' && $record->recorded_at)
            : $this->records()->where('outcome', 'collected')->latest('recorded_at')->first();

        return $record?->recorded_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    public function route()
    {
        return $this->belongsTo(CollectionRoute::class, 'collection_route_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function records()
    {
        return $this->hasMany(CollectionRecord::class)->latest('recorded_at');
    }

    public function collectorDuesOn(CarbonInterface $asOf): array
    {
        $this->loadMissing('client.loans.installments');

        $overdue = collect();
        $dueToday = collect();

        foreach ($this->client?->loans ?? [] as $loan) {
            if (! in_array($loan->status, Loan::COLLECTIBLE_STATUSES, true)) {
                continue;
            }

            foreach ($loan->installments as $installment) {
                if (! $installment->due_date) {
                    continue;
                }

                $row = [
                    'id' => $installment->id,
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->number,
                    'number' => $installment->number,
                    'due_date' => $installment->due_date->toDateString(),
                    'outstanding' => $installment->outstandingAmount(),
                    'mora' => $installment->moraOutstanding(),
                ];

                if ($installment->isOverdueOn($asOf)) {
                    $overdue->push($row + [
                        'kind' => 'overdue',
                        'days' => $installment->daysOverdueOn($asOf),
                    ]);

                    continue;
                }

                if (
                    ! $installment->isExcludedFromCollection()
                    && bccomp($installment->outstandingAmount(), '0.00', 2) === 1
                    && $installment->calendarDate($installment->due_date)->equalTo($installment->calendarDate($asOf))
                ) {
                    $dueToday->push($row + ['kind' => 'due_today', 'days' => 0]);
                }
            }
        }

        $sum = fn ($rows, string $field = 'outstanding') => $rows->reduce(fn (string $total, array $row) => bcadd($total, $row[$field], 2), '0.00');

        return [
            'overdue' => $overdue->values()->all(),
            'due_today' => $dueToday->values()->all(),
            'overdue_total' => $sum($overdue),
            'due_today_total' => $sum($dueToday),
            'overdue_mora_total' => $sum($overdue, 'mora'),
            'due_today_mora_total' => $sum($dueToday, 'mora'),
            'total' => bcadd($sum($overdue), $sum($dueToday), 2),
        ];
    }
}
