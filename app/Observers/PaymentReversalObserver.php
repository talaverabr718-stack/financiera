<?php

namespace App\Observers;

use App\Models\PaymentReversal;
use App\Services\DelinquencyTrackingService;
use Illuminate\Support\Facades\DB;

class PaymentReversalObserver
{
    public function created(PaymentReversal $reversal): void
    {
        $recalculate = function () use ($reversal) {
            $loan = $reversal->payment()->with('loan')->first()?->loan;
            if (! $loan) {
                return;
            }

            app(DelinquencyTrackingService::class)->recalculateLoan(
                $loan,
                now(),
                ['trigger' => 'reversal', 'actor_id' => $reversal->created_by]
            );
        };

        if (app()->runningUnitTests()) {
            $recalculate();

            return;
        }

        DB::afterCommit($recalculate);
    }
}
