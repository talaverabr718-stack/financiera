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

    public const PRODUCT_METHODS = [
        'french' => 'level_payment',
        'flat' => 'flat_interest',
        'declining_balance' => 'constant_principal',
    ];

    public const MAX_PERIODS = 365;

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

    public function resolveCalculatorMethod(?string $method): string
    {
        if ($method && isset(self::METHODS[$method])) {
            return $method;
        }

        return self::PRODUCT_METHODS[$method] ?? 'level_payment';
    }

    public function projectFromInstallment(array $data): array
    {
        $principal = round((float) ($data['principal'] ?? 0), 2);
        $installment = round((float) ($data['installment'] ?? 0), 2);
        $annualRate = (float) ($data['annual_rate'] ?? 0);
        $frequency = $data['frequency'] ?? '';
        $method = $this->resolveCalculatorMethod($data['method'] ?? null);
        $empty = [
            'periods' => 0,
            'regular_payment' => '0.00',
            'last_payment' => '0.00',
            'total_interest' => '0.00',
            'total_payment' => '0.00',
            'error' => null,
        ];

        if ($principal <= 0 || $installment <= 0 || ! isset(self::FREQUENCIES[$frequency])) {
            return $empty;
        }

        $periods = (int) ceil($principal / $installment);
        if ($periods < 1) {
            return $empty;
        }
        if ($periods > self::MAX_PERIODS) {
            return array_merge($empty, ['error' => 'Aumenta el monto de cada cuota. El crédito no puede superar 365 pagos.']);
        }

        $schedule = $this->calculate([
            'principal' => $principal,
            'annual_rate' => $annualRate,
            'periods' => $periods,
            'method' => $method,
            'frequency' => $frequency,
            'first_payment_date' => $data['first_payment_date'] ?? now()->toDateString(),
        ]);
        $firstPayment = $schedule['rows'][0]['payment'] ?? '0.00';
        $lastPayment = $schedule['rows'][$periods - 1]['payment'] ?? $firstPayment;

        return [
            'periods' => $periods,
            'regular_payment' => $firstPayment,
            'last_payment' => $lastPayment,
            'total_interest' => $schedule['total_interest'],
            'total_payment' => $schedule['total_payment'],
            'error' => null,
        ];
    }
}
