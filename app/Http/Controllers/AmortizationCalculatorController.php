<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculateAmortizationRequest;
use App\Services\AmortizationCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmortizationCalculatorController extends Controller
{
    public function __construct(private AmortizationCalculator $calculator) {}

    public function __invoke(Request $request): Response
    {
        $frequency = $request->input('frequency', 'weekly');
        $input = [
            'principal' => $request->input('principal'),
            'annual_rate' => $request->input('annual_rate'),
            'periods' => $request->input('periods'),
            'term_months' => $request->input('term_months', 3),
            'frequency' => $frequency,
            'method' => 'monthly_flat',
            'first_payment_date' => $request->input('first_payment_date', match ($frequency) {
                'daily' => today()->addDay()->format('Y-m-d'),
                'weekly' => today()->addWeek()->format('Y-m-d'),
                'biweekly' => today()->addDays(15)->format('Y-m-d'),
                default => today()->addMonth()->format('Y-m-d'),
            }),
        ];

        return Inertia::render('Amortization/Index', [
            'input' => $input,
            'frequencies' => AmortizationCalculator::FREQUENCIES,
            'calculateUrl' => route('amortization.calculate'),
        ]);
    }

    public function calculate(CalculateAmortizationRequest $request): JsonResponse
    {
        $input = $request->validated();

        return response()->json($this->calculator->calculate($input));
    }
}
