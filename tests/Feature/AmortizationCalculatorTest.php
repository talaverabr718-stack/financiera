<?php

namespace Tests\Feature;

use App\Services\AmortizationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AmortizationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_module_renders_without_persisting_a_loan(): void
    {
        $this->get(route('amortization.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Amortization/Index')
            ->has('methods', 3)
            ->has('frequencies', 4)
            ->has('navigation', 4)
            ->has('navigation.1.items', 4)
            ->where('calculateUrl', route('amortization.calculate'))
        );

        $this->postJson(route('amortization.calculate'), [
            'principal' => '12000.00',
            'annual_rate' => '12.00',
            'periods' => 12,
            'frequency' => 'monthly',
            'method' => 'level_payment',
            'first_payment_date' => '2026-09-30',
        ])->assertOk()->assertJsonPath('rows.0.payment', '1066.19')->assertJsonCount(12, 'rows');

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_level_payment_schedule_reconciles_principal_and_balance(): void
    {
        $result = app(AmortizationCalculator::class)->calculate([
            'principal' => '10000', 'annual_rate' => '18', 'periods' => 10,
            'frequency' => 'monthly', 'method' => 'level_payment',
            'first_payment_date' => '2026-09-15',
        ]);

        $principalPaid = array_sum(array_column($result['rows'], 'principal'));
        $this->assertEqualsWithDelta(10000, $principalPaid, 0.01);
        $this->assertSame('0.00', $result['rows'][9]['closing_balance']);
    }

    public function test_zero_interest_divides_principal_without_errors(): void
    {
        $result = app(AmortizationCalculator::class)->calculate([
            'principal' => '1200', 'annual_rate' => '0', 'periods' => 12,
            'frequency' => 'monthly', 'method' => 'level_payment',
            'first_payment_date' => '2026-09-01',
        ]);

        $this->assertSame('0.00', $result['total_interest']);
        $this->assertSame('100.00', $result['rows'][0]['payment']);
    }

    public function test_invalid_limits_are_rejected(): void
    {
        $this->post(route('amortization.calculate'), [
            'principal' => 0, 'annual_rate' => -1, 'periods' => 601,
            'frequency' => 'unknown', 'method' => 'unknown',
            'first_payment_date' => 'invalid',
        ])->assertSessionHasErrors(['principal', 'annual_rate', 'periods', 'frequency', 'method', 'first_payment_date']);
    }
}
