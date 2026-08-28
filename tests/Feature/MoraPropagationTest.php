<?php

namespace Tests\Feature;

use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\DelinquencyTrackingService;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MoraPropagationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Managua']);
        date_default_timezone_set('America/Managua');
        $this->seed(ClientModuleSeeder::class);
    }

    public function test_applied_mora_surfaces_on_collector_routes_portfolio_dashboard_and_client_modules(): void
    {
        [$stop, $loan, $installment] = $this->overdueInstallmentWithMora('100.00', '15.00', 2);
        $loan->update(['delinquency_balance' => '15.00']);

        $this->assertSame('15.00', $installment->fresh()->moraOutstanding());
        $this->assertSame('115.00', $installment->fresh()->outstandingAmount());

        $this->get(route('collections.index', [
            'date' => today()->format('Y-m-d'),
            'agenda_route' => $stop->collection_route_id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedRoute.stops', fn ($stops) => collect($stops)->contains(fn ($item) => (int) $item['client_id'] === $stop->client_id
                && ($item['dues']['overdue'][0]['mora'] ?? null) === '15.00'
                && ($item['dues']['overdue'][0]['outstanding'] ?? null) === '115.00'
                && ($item['dues']['overdue_mora_total'] ?? null) === '15.00'))
            ->where('lateInstallments', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['id'] === $installment->id
                && ($row['mora'] ?? null) === '15.00'
                && ($row['outstanding'] ?? null) === '115.00')));

        $this->get(route('routes.index', ['date' => today()->format('Y-m-d'), 'route' => $stop->collection_route_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedRoute.stops', fn ($stops) => collect($stops)->contains(fn ($item) => (int) $item['client_id'] === $stop->client_id
                    && ($item['dues']['overdue'][0]['mora'] ?? null) === '15.00')));

        $this->get(route('loans.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('loans.data', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['id'] === $loan->id
                && (string) $row['delinquency_balance'] === '15.00')));

        $this->get(route('loans.show', $loan))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('delinquency.total_mora', '15.00')
            ->where('delinquency.ledger.0.mora_outstanding', '15.00')
            ->where('delinquency.ledger.0.mora_amount', '15.00'));

        $this->get(route('clients.show', $loan->client))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('delinquency.total_mora', '15.00')
            ->where('delinquency.overdue_balance', '115.00'));

        $this->get(route('credit-history.show', $loan->client))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('loans', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['id'] === $loan->id
                && (string) $row['mora'] === '15.00')));

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('overdueWatch', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['id'] === $installment->id
                && ($row['mora'] ?? null) === '15.00'
                && ($row['outstanding'] ?? null) === '115.00')));
    }

    public function test_recalculated_mora_is_included_in_collector_dues_and_delinquency_index(): void
    {
        [$stop, $loan] = $this->overdueInstallmentWithMora('1000.00', '0.00', 6);
        app(DelinquencyTrackingService::class)->recalculateLoan($loan->fresh('installments'), now(), [
            'daily_rate' => '1',
            'trigger' => 'manual',
            'actor_id' => auth()->id(),
        ]);

        $loan = $loan->fresh('installments');
        $this->assertSame('60.00', (string) $loan->installments->first()->delinquency_due);
        $this->assertSame('60.00', (string) $loan->delinquency_balance);

        $dues = $stop->fresh()->collectorDuesOn(today());
        $this->assertSame('60.00', $dues['overdue'][0]['mora']);
        $this->assertSame('1060.00', $dues['overdue'][0]['outstanding']);

        $this->get(route('delinquency.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cases.data', fn ($rows) => collect($rows)->contains(fn ($row) => (int) $row['loan_id'] === $loan->id
                && (string) data_get($row, 'loan.delinquency_balance') === '60.00')));
    }

    public function test_partial_mora_payment_reduces_collector_mora_without_hiding_the_installment(): void
    {
        [$stop, $loan, $installment] = $this->overdueInstallmentWithMora('100.00', '15.00', 2);
        $loan->update(['delinquency_balance' => '15.00']);
        $installment->update([
            'delinquency_paid' => '10.00',
            'paid_amount' => '10.00',
        ]);

        $dues = $stop->fresh()->collectorDuesOn(today());
        $this->assertSame('5.00', $dues['overdue'][0]['mora']);
        $this->assertSame('105.00', $dues['overdue'][0]['outstanding']);
        $this->assertSame('5.00', $dues['overdue_mora_total']);
    }

    public function test_collection_payment_applies_to_mora_first_and_clears_it_from_dues(): void
    {
        [$stop, $loan] = $this->overdueInstallmentWithMora('100.00', '15.00', 2);
        $loan->update([
            'delinquency_balance' => '15.00',
            'principal_balance' => '100.00',
        ]);

        $this->post(route('collections.store', $stop), [
            'outcome' => 'collected',
            'loan_id' => $loan->id,
            'amount' => '15.00',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_allocations', [
            'installment_id' => $loan->installments()->first()->id,
            'component' => 'delinquency',
            'amount' => '15.00',
        ]);

        $dues = $stop->fresh()->collectorDuesOn(today());
        $this->assertSame('0.00', $dues['overdue'][0]['mora']);
        $this->assertSame('100.00', $dues['overdue'][0]['outstanding']);
        $this->assertSame('0.00', (string) $loan->fresh()->delinquency_balance);
    }

    public function test_mora_is_summed_per_overdue_installment_and_omitted_when_waived_or_fully_paid(): void
    {
        $stop = $this->pendingStop();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $this->createInstallment($loan, 1, today()->subDays(3), '80.00', ['delinquency_due' => '8.00']);
        $this->createInstallment($loan, 2, today()->subDays(1), '50.00', ['delinquency_due' => '5.00']);
        $this->createInstallment($loan, 3, today()->subDays(4), '40.00', [
            'delinquency_due' => '20.00',
            'status' => 'waived',
        ]);
        $this->createInstallment($loan, 4, today()->subDays(2), '30.00', [
            'delinquency_due' => '9.00',
            'delinquency_paid' => '9.00',
            'principal_paid' => '30.00',
            'paid_amount' => '39.00',
            'status' => 'paid',
        ]);
        $this->createInstallment($loan, 5, today(), '25.00', ['delinquency_due' => '0.00']);

        $dues = $stop->fresh()->collectorDuesOn(today());

        $this->assertCount(2, $dues['overdue']);
        $this->assertSame('13.00', $dues['overdue_mora_total']);
        $this->assertSame('143.00', $dues['overdue_total']);
        $this->assertCount(1, $dues['due_today']);
        $this->assertSame('0.00', $dues['due_today'][0]['mora']);
        $this->assertSame('25.00', $dues['due_today_total']);
        $this->assertSame('168.00', $dues['total']);
    }

    public function test_mora_outstanding_never_goes_negative_when_delinquency_is_overpaid(): void
    {
        $loan = Loan::firstOrFail();
        $installment = $this->createInstallment($loan, 1, today()->subDay(), '100.00', [
            'delinquency_due' => '10.00',
            'delinquency_paid' => '12.00',
            'paid_amount' => '12.00',
        ]);

        $this->assertSame('0.00', $installment->moraOutstanding());
    }

    public function test_recalc_does_not_drop_delinquency_due_below_amount_already_paid(): void
    {
        $loan = Loan::firstOrFail();
        $installment = $this->createInstallment($loan, 1, today()->subDays(6), '1000.00', [
            'delinquency_due' => '60.00',
            'delinquency_paid' => '60.00',
            'paid_amount' => '60.00',
        ]);
        $loan->update([
            'principal_balance' => '1000.00',
            'delinquency_balance' => '0.00',
            'delinquency_daily_rate' => '1.000000',
        ]);

        app(DelinquencyTrackingService::class)->recalculateLoan($loan->fresh('installments'), now(), [
            'daily_rate' => '0.5',
            'trigger' => 'manual',
            'actor_id' => auth()->id(),
        ]);

        $installment = $installment->fresh();
        $loan = $loan->fresh();

        $this->assertSame('60.00', (string) $installment->delinquency_due);
        $this->assertSame('0.00', $installment->moraOutstanding());
        $this->assertSame('1000.00', $installment->outstandingAmount());
        $this->assertSame('0.00', (string) $loan->delinquency_balance);
        $this->assertSame(
            bcadd(bcadd((string) $loan->principal_balance, (string) $loan->interest_balance, 2), (string) $loan->fee_balance, 2),
            $loan->outstanding_balance
        );
    }

    public function test_due_today_installment_can_show_mora_if_it_was_already_applied(): void
    {
        $stop = $this->pendingStop();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $this->createInstallment($loan, 1, today(), '80.00', ['delinquency_due' => '4.00']);

        $dues = $stop->fresh()->collectorDuesOn(today());

        $this->assertSame([], $dues['overdue']);
        $this->assertSame('4.00', $dues['due_today'][0]['mora']);
        $this->assertSame('84.00', $dues['due_today'][0]['outstanding']);
        $this->assertSame('4.00', $dues['due_today_mora_total']);
    }

    /**
     * @return array{0: CollectionRouteStop, 1: Loan, 2: LoanInstallment}
     */
    private function overdueInstallmentWithMora(string $principal, string $mora, int $daysOverdue): array
    {
        $stop = $this->pendingStop();
        $loan = Loan::where('client_id', $stop->client_id)->firstOrFail();
        $installment = $this->createInstallment($loan, 1, today()->subDays($daysOverdue), $principal, [
            'delinquency_due' => $mora,
        ]);

        return [$stop, $loan, $installment];
    }

    private function pendingStop()
    {
        return CollectionRoute::with('stops')->firstOrFail()->stops->where('status', 'pending')->firstOrFail();
    }

    private function createInstallment(Loan $loan, int $number, $dueDate, string $principal, array $extra = []): LoanInstallment
    {
        return LoanInstallment::create(array_merge([
            'loan_id' => $loan->id,
            'number' => $number,
            'due_date' => $dueDate,
            'principal_due' => $principal,
            'interest_due' => '0.00',
            'fees_due' => '0.00',
            'delinquency_due' => '0.00',
            'principal_paid' => '0.00',
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
            'delinquency_paid' => '0.00',
            'paid_amount' => '0.00',
            'status' => 'pending',
        ], $extra));
    }
}
