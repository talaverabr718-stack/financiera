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
            ->missing('methods')
            ->has('frequencies', 4)
            ->has('navigation', 4)
            ->has('navigation.1.items', 6)
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

    public function test_monthly_simple_interest_matches_the_approved_weekly_example(): void
    {
        $result = app(AmortizationCalculator::class)->calculate([
            'principal' => '5000.00',
            'annual_rate' => '16.00',
            'term_months' => 3,
            'frequency' => 'weekly',
            'method' => 'monthly_flat',
            'first_payment_date' => '2026-09-04',
        ]);

        $this->assertSame(12, $result['periods']);
        $this->assertSame(3, $result['term_months']);
        $this->assertSame('48.000000', $result['total_rate']);
        $this->assertSame('2400.00', $result['total_interest']);
        $this->assertSame('7400.00', $result['total_payment']);
        $this->assertSame('616.67', $result['average_payment']);
        $this->assertSame('616.67', $result['rows'][0]['payment']);
        $this->assertSame('616.63', $result['rows'][11]['payment']);
        $this->assertSame('0.00', $result['rows'][11]['closing_balance']);
    }

    public function test_monthly_simple_interest_uses_two_biweekly_payments_per_month(): void
    {
        $this->postJson(route('amortization.calculate'), [
            'principal' => '5000.00',
            'annual_rate' => '16.00',
            'term_months' => 3,
            'frequency' => 'biweekly',
            'method' => 'monthly_flat',
            'first_payment_date' => '2026-09-15',
        ])->assertOk()
            ->assertJsonPath('periods', 6)
            ->assertJsonPath('total_interest', '2400.00')
            ->assertJsonPath('total_payment', '7400.00')
            ->assertJsonCount(6, 'rows');
    }

    public function test_installment_projection_includes_interest_in_the_payment_count(): void
    {
        $calculator = app(AmortizationCalculator::class);

        $withoutInterest = $calculator->projectFromInstallment([
            'principal' => '10000', 'installment' => '1000', 'annual_rate' => '0',
            'frequency' => 'monthly', 'method' => 'french',
        ]);
        $this->assertSame(10, $withoutInterest['periods']);
        $this->assertSame('0.00', $withoutInterest['total_interest']);
        $this->assertSame('1000.00', $withoutInterest['regular_payment']);
        $this->assertSame('10000.00', $withoutInterest['total_payment']);

        $withInterest = $calculator->projectFromInstallment([
            'principal' => '10000', 'installment' => '1000', 'annual_rate' => '12',
            'frequency' => 'monthly', 'method' => 'french',
        ]);
        $this->assertSame(10, $withInterest['periods']);
        $this->assertSame(1, bccomp($withInterest['regular_payment'], '1000.00', 2));
        $this->assertSame(1, bccomp($withInterest['total_interest'], '0.00', 2));
        $this->assertSame(1, bccomp($withInterest['total_payment'], '10000.00', 2));

        $tooMany = $calculator->projectFromInstallment([
            'principal' => '10000', 'installment' => '20', 'annual_rate' => '12',
            'frequency' => 'monthly', 'method' => 'french',
        ]);
        $this->assertSame(0, $tooMany['periods']);
        $this->assertNotNull($tooMany['error']);
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
