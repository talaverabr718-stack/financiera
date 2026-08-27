<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class AmortizationCalculator
{
    public const METHODS = [
        'level_payment' => 'Cuota nivelada (francés)',
        'constant_principal' => 'Capital constante',
        'flat_interest' => 'Interés plano',
    ];

    public const FREQUENCIES = [
        'daily' => ['label' => 'Diaria', 'periods_per_year' => 365],
        'weekly' => ['label' => 'Semanal', 'periods_per_year' => 52],
        'biweekly' => ['label' => 'Quincenal', 'periods_per_year' => 24],
        'monthly' => ['label' => 'Mensual', 'periods_per_year' => 12],
    ];

    public function calculate(array $data): array
    {
        $principal = round((float) $data['principal'], 2);
        $annualRate = (float) $data['annual_rate'];
        $periods = (int) $data['periods'];
        $method = $data['method'];
        $frequency = $data['frequency'];
        $firstPaymentDate = CarbonImmutable::parse($data['first_payment_date']);

        if (! isset(self::METHODS[$method], self::FREQUENCIES[$frequency])) {
            throw new InvalidArgumentException('Método o frecuencia no soportados.');
        }

        $periodicRate = $annualRate / 100 / self::FREQUENCIES[$frequency]['periods_per_year'];
        $balance = $principal;
        $rows = [];
        $totalInterest = 0.0;
        $totalPayment = 0.0;

        $levelPayment = $method === 'level_payment'
            ? ($periodicRate == 0.0 ? $principal / $periods : $principal * $periodicRate / (1 - pow(1 + $periodicRate, -$periods)))
            : 0.0;
        $constantPrincipal = $principal / $periods;
        $flatInterest = $principal * ($annualRate / 100) / self::FREQUENCIES[$frequency]['periods_per_year'];

        for ($number = 1; $number <= $periods; $number++) {
            $openingBalance = $balance;
            $interest = match ($method) {
                'flat_interest' => $flatInterest,
                default => $openingBalance * $periodicRate,
            };
            $principalPayment = match ($method) {
                'level_payment' => $levelPayment - $interest,
                default => $constantPrincipal,
            };

            $interest = round($interest, 2);
            $principalPayment = $number === $periods ? $balance : min($balance, round($principalPayment, 2));
            $payment = round($principalPayment + $interest, 2);
            $balance = round(max(0, $balance - $principalPayment), 2);
            $totalInterest = round($totalInterest + $interest, 2);
            $totalPayment = round($totalPayment + $payment, 2);

            $rows[] = [
                'number' => $number,
                'date' => $this->paymentDate($firstPaymentDate, $frequency, $number - 1),
                'opening_balance' => number_format($openingBalance, 2, '.', ''),
                'principal' => number_format($principalPayment, 2, '.', ''),
                'interest' => number_format($interest, 2, '.', ''),
                'payment' => number_format($payment, 2, '.', ''),
                'closing_balance' => number_format($balance, 2, '.', ''),
            ];
        }

        return [
            'principal' => number_format($principal, 2, '.', ''),
            'annual_rate' => number_format($annualRate, 6, '.', ''),
            'periodic_rate' => number_format($periodicRate * 100, 6, '.', ''),
            'total_interest' => number_format($totalInterest, 2, '.', ''),
            'total_payment' => number_format($totalPayment, 2, '.', ''),
            'average_payment' => number_format($totalPayment / $periods, 2, '.', ''),
            'rows' => $rows,
        ];
    }

    private function paymentDate(CarbonImmutable $firstDate, string $frequency, int $offset): CarbonImmutable
    {
        return match ($frequency) {
            'daily' => $firstDate->addDays($offset),
            'weekly' => $firstDate->addWeeks($offset),
            'biweekly' => $firstDate->addDays(15 * $offset),
            'monthly' => $firstDate->addMonthsNoOverflow($offset),
        };
    }
}
