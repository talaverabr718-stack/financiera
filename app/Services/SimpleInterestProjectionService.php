<?php

namespace App\Services;

use InvalidArgumentException;

class SimpleInterestProjectionService
{
    public const TERM_UNITS = [
        'daily' => 'Días',
        'weekly' => 'Semanas',
        'monthly' => 'Meses',
        'yearly' => 'Años',
    ];

    public const PAYMENT_FREQUENCIES = ['daily', 'weekly', 'biweekly', 'monthly'];

    public const MAX_PAYMENTS = 365;

    public const INTEREST_METHOD = 'flat';

    private const PAYMENT_RATIOS = [
        'daily' => [
            'daily' => [1, 1],
            'weekly' => [1, 7],
            'biweekly' => [1, 14],
            'monthly' => [1, 30],
        ],
        'weekly' => [
            'daily' => [7, 1],
            'weekly' => [1, 1],
            'biweekly' => [1, 2],
            'monthly' => [1, 4],
        ],
        'monthly' => [
            'daily' => [30, 1],
            'weekly' => [4, 1],
            'biweekly' => [2, 1],
            'monthly' => [1, 1],
        ],
        'yearly' => [
            'daily' => [360, 1],
            'weekly' => [48, 1],
            'biweekly' => [24, 1],
            'monthly' => [12, 1],
        ],
    ];

    /**
     * Proyecta interés simple plano. La tasa corresponde a cada unidad del plazo.
     *
     * @return array{payments:int,total_rate:string,total_interest:string,total_payable:string,installment_amount:string,last_payment:string}
     */
    public function calculate(string|int|float $principal, string|int|float $rate, int $termValue, string $termUnit, string $paymentFrequency): array
    {
        $principal = round((float) $principal, 2);
        $rate = (float) $rate;

        if ($principal <= 0 || $rate < 0 || $termValue < 1 || ! isset(self::PAYMENT_RATIOS[$termUnit][$paymentFrequency])) {
            throw new InvalidArgumentException('Los datos de la proyección financiera no son válidos.');
        }

        [$numerator, $denominator] = self::PAYMENT_RATIOS[$termUnit][$paymentFrequency];
        $payments = (int) ceil(($termValue * $numerator) / $denominator);

        if ($payments < 1 || $payments > self::MAX_PAYMENTS) {
            throw new InvalidArgumentException('El plazo no puede superar 365 pagos.');
        }

        $totalRate = $rate * $termValue;
        $totalInterest = round($principal * ($totalRate / 100), 2);
        $totalPayable = round($principal + $totalInterest, 2);
        $installment = round($totalPayable / $payments, 2);
        $lastPayment = round($totalPayable - ($installment * ($payments - 1)), 2);

        return [
            'payments' => $payments,
            'total_rate' => number_format($totalRate, 6, '.', ''),
            'total_interest' => number_format($totalInterest, 2, '.', ''),
            'total_payable' => number_format($totalPayable, 2, '.', ''),
            'installment_amount' => number_format($installment, 2, '.', ''),
            'last_payment' => number_format($lastPayment, 2, '.', ''),
        ];
    }
}