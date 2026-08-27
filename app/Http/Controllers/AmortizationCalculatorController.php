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
        $input = [
            'principal' => $request->input('principal'),
            'annual_rate' => $request->input('annual_rate'),
            'periods' => $request->input('periods'),
            'frequency' => $request->input('frequency', 'monthly'),
            'method' => $request->input('method', 'level_payment'),
            'first_payment_date' => $request->input('first_payment_date', today()->addMonth()->format('Y-m-d')),
        ];

        return Inertia::render('Amortization/Index', [
            'input' => $input,
            'methods' => AmortizationCalculator::METHODS,
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
