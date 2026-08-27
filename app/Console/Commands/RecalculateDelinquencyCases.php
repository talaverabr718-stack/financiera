<?php

namespace App\Console\Commands;

use App\Services\DelinquencyTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecalculateDelinquencyCases extends Command
{
    protected $signature = 'delinquency:recalculate {--date= : Fecha de cálculo (Y-m-d) en la zona horaria de la aplicación} {--loan= : Recalcular un crédito específico}';

    protected $description = 'Recalcula expedientes de mora a partir de cuotas vencidas con saldo pendiente';

    public function handle(DelinquencyTrackingService $delinquency): int
    {
        $asOf = $this->option('date')
            ? $delinquency->calendarDate(Carbon::parse($this->option('date'), config('app.timezone')))
            : $delinquency->calendarDate(now());

        if ($loanId = $this->option('loan')) {
            $loan = \App\Models\Loan::findOrFail($loanId);
            $delinquency->recalculateLoan($loan, $asOf, ['trigger' => 'manual']);
            $this->info("Mora recalculada para el crédito {$loan->number} al {$asOf->toDateString()}.");

            return self::SUCCESS;
        }

        $result = $delinquency->recalculateDueLoans($asOf, ['trigger' => 'schedule']);
        $this->info("Créditos procesados: {$result['processed']}. Errores: {$result['failed']}. Fecha: {$asOf->toDateString()}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
