<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\DelinquencyTrackingService;
use Illuminate\Support\Facades\DB;

class PaymentObserver
{
    public function saved(Payment $payment): void
    {
        if (! $payment->loan_id) {
            return;
        }

        $recalculate = function () use ($payment) {
            $payment->refresh();
            if (! $payment->loan_id || $payment->reversal()->exists()) {
                return;
            }

            app(DelinquencyTrackingService::class)->recalculateLoan(
                $payment->loan,
                now(),
                ['trigger' => 'payment', 'actor_id' => $payment->created_by]
            );
        };

        if (app()->runningUnitTests()) {
            $recalculate();

            return;
        }

        DB::afterCommit($recalculate);
    }
}
