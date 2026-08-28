<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\DelinquencyAccrual;
use App\Models\DelinquencyCase;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReversal;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\DelinquencyTrackingService;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DelinquencyTrackingTest extends TestCase
{
    use RefreshDatabase;

    private DelinquencyTrackingService $delinquency;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Managua']);
        date_default_timezone_set('America/Managua');
        $this->seed(ClientModuleSeeder::class);
        $this->delinquency = app(DelinquencyTrackingService::class);
    }

    public function test_installment_is_not_delinquent_on_its_due_date(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-10 09:00:00'));

        $this->assertNull($this->delinquency->recalculateLoan($loan->fresh('installments')));
        $this->assertDatabaseCount('delinquency_cases', 0);
        $this->assertFalse($this->delinquency->summarizeLoan($loan->fresh('installments'))['in_arrears']);
    }

    public function test_installment_enters_delinquency_the_day_after_due_date(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-11 00:01:00'));

        $case = $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->assertSame('MORA-001', $case->code);
        $this->assertSame('active', $case->status);
        $this->assertSame(1, $case->current_days);
        $this->assertSame('2026-08-11', $case->started_on->toDateString());
        $this->assertSame('delinquent', $loan->fresh()->status);
    }

    public function test_correlative_codes_are_unique(): void
    {
        $first = $this->loan();
        $second = $this->secondLoan($first->client);
        $this->addInstallment($first, 1, '2026-08-10', '1000.00');
        $this->addInstallment($second, 1, '2026-08-10', '800.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));

        $one = $this->delinquency->recalculateLoan($first->fresh('installments'));
        $two = $this->delinquency->recalculateLoan($second->fresh('installments'));

        $this->assertSame('MORA-001', $one->code);
        $this->assertSame('MORA-002', $two->code);
        $this->assertNotSame($one->code, $two->code);
    }

    public function test_daily_recalculation_increments_days_without_duplicating_the_case(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-11 08:00:00'));
        $this->artisan('delinquency:recalculate')->assertSuccessful();
        $this->assertSame(1, DelinquencyCase::firstOrFail()->current_days);

        $this->travelTo($this->at('2026-08-15 08:00:00'));
        $this->artisan('delinquency:recalculate')->assertSuccessful();
        $this->artisan('delinquency:recalculate')->assertSuccessful();

        $this->assertDatabaseCount('delinquency_cases', 1);
        $this->assertSame(5, DelinquencyCase::firstOrFail()->current_days);
        $this->assertSame('active', DelinquencyCase::firstOrFail()->status);
    }

    public function test_partial_payment_keeps_delinquency_active(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->applyPayment($loan, $installment, '400.00');

        $case = DelinquencyCase::firstOrFail();
        $this->assertSame('active', $case->status);
        $this->assertSame('600.00', $case->fresh()->overdue_balance);
        $this->assertSame(1, $case->fresh()->overdue_installment_count);
    }

    public function test_full_payment_of_the_only_overdue_installment_resolves_delinquency(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->applyPayment($loan, $installment, '1000.00');

        $case = DelinquencyCase::firstOrFail();
        $this->assertSame('resolved', $case->status);
        $this->assertSame('2026-08-12', $case->resolved_on->toDateString());
        $this->assertSame(2, $case->total_days);
        $this->assertSame('active', $loan->fresh()->status);
        $this->assertDatabaseCount('delinquency_cases', 1);
    }

    public function test_paying_the_oldest_installment_recalculates_days_from_the_next_overdue(): void
    {
        $loan = $this->loan();
        $oldest = $this->addInstallment($loan, 1, '2026-08-01', '1000.00');
        $this->addInstallment($loan, 2, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-15 10:00:00'));
        $case = $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $this->assertSame(14, $case->current_days);
        $this->assertSame(2, $case->overdue_installment_count);

        $this->applyPayment($loan, $oldest, '1000.00');

        $case = $case->fresh('items');
        $this->assertSame('active', $case->status);
        $this->assertSame(5, $case->current_days);
        $this->assertSame(1, $case->overdue_installment_count);
        $this->assertSame('2026-08-10', $case->oldest_due_on->toDateString());
        $this->assertSame('MORA-001', $case->code);
    }

    public function test_several_overdue_installments_share_a_single_active_case(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-01', '500.00');
        $this->addInstallment($loan, 2, '2026-08-08', '500.00');
        $this->addInstallment($loan, 3, '2026-08-15', '500.00');
        $this->travelTo($this->at('2026-08-20 10:00:00'));

        $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->assertDatabaseCount('delinquency_cases', 1);
        $case = DelinquencyCase::firstOrFail();
        $this->assertSame(3, $case->overdue_installment_count);
        $this->assertSame('1500.00', $case->overdue_balance);
        $this->assertCount(3, $case->items);
    }

    public function test_a_client_can_have_independent_cases_per_loan(): void
    {
        $first = $this->loan();
        $second = $this->secondLoan($first->client);
        $this->addInstallment($first, 1, '2026-08-10', '1000.00');
        $this->addInstallment($second, 1, '2026-08-05', '700.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));

        $this->delinquency->recalculateLoan($first->fresh('installments'));
        $this->delinquency->recalculateLoan($second->fresh('installments'));

        $this->assertSame(2, DelinquencyCase::where('client_id', $first->client_id)->where('status', 'active')->count());
        $this->assertSame(2, DelinquencyCase::where('loan_id', $first->id)->value('current_days'));
        $this->assertSame(7, DelinquencyCase::where('loan_id', $second->id)->value('current_days'));
    }

    public function test_reversing_a_payment_restores_the_same_delinquency_case(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $payment = $this->applyPayment($loan, $installment, '1000.00');
        $this->assertSame('resolved', DelinquencyCase::firstOrFail()->status);

        $this->reversePayment($payment, $installment);

        $case = DelinquencyCase::firstOrFail();
        $this->assertSame('MORA-001', $case->code);
        $this->assertSame('active', $case->status);
        $this->assertNull($case->resolved_on);
        $this->assertDatabaseCount('delinquency_cases', 1);
    }

    public function test_a_later_default_opens_a_new_case_without_erasing_history(): void
    {
        $loan = $this->loan();
        $first = $this->addInstallment($loan, 1, '2026-08-01', '1000.00');
        $this->addInstallment($loan, 2, '2026-08-20', '1000.00');
        $this->travelTo($this->at('2026-08-05 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $this->applyPayment($loan, $first, '1000.00');
        $this->assertSame('resolved', DelinquencyCase::firstOrFail()->status);

        $this->travelTo($this->at('2026-08-21 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->assertDatabaseCount('delinquency_cases', 2);
        $this->assertSame('resolved', DelinquencyCase::where('code', 'MORA-001')->value('status'));
        $this->assertSame('active', DelinquencyCase::where('code', 'MORA-002')->value('status'));
    }

    public function test_cancelled_or_refinanced_installments_do_not_create_delinquency(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-01', '1000.00', '0.00', 'cancelled');
        $this->addInstallment($loan, 2, '2026-08-01', '1000.00', '0.00', 'refinanced');
        $this->travelTo($this->at('2026-08-10 10:00:00'));

        $this->assertNull($this->delinquency->recalculateLoan($loan->fresh('installments')));
        $this->assertDatabaseCount('delinquency_cases', 0);
    }

    public function test_unique_constraints_prevent_duplicate_active_cases_and_codes(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->expectException(QueryException::class);
        DelinquencyCase::create([
            'code' => 'MORA-001',
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'status' => 'active',
            'started_on' => '2026-08-11',
            'oldest_due_on' => '2026-08-10',
            'last_calculated_on' => '2026-08-12',
            'current_days' => 1,
            'total_days' => 1,
            'overdue_installment_count' => 1,
            'overdue_balance' => '1000.00',
            'active_guard' => 'ACTIVE',
        ]);
    }

    public function test_date_boundaries_use_application_timezone_not_utc(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');

        $this->travelTo(Carbon::parse('2026-08-11 05:00:00', 'UTC'));
        $this->assertNull($this->delinquency->recalculateLoan($loan->fresh('installments')));

        $this->travelTo(Carbon::parse('2026-08-11 06:01:00', 'UTC'));
        $case = $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $this->assertNotNull($case);
        $this->assertSame(1, $case->current_days);
    }

    public function test_index_lists_active_cases_and_loan_show_displays_delinquency(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1250.00');
        $this->travelTo($this->at('2026-08-16 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->get(route('delinquency.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Delinquency/Index')
            ->where('cases.data.0.code', 'MORA-001')
            ->where('cases.data.0.current_days', 6)
            ->where('endpoints.recalculate', route('delinquency.recalculate')));

        $this->get(route('loans.show', $loan))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Loans/Show')->where('delinquency.in_arrears', true)
            ->where('delinquency.code', 'MORA-001')
            ->where('delinquency.ledger.0.days_overdue', 6));

        $this->get(route('clients.show', $loan->client))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->where('delinquency.in_arrears', true)
                ->has('delinquency.ledger', 1)
                ->where('delinquency.active_cases.0.code', 'MORA-001')
                ->where('delinquency.ledger.0.days_overdue', 6)
                ->where('delinquency.total_mora', '0.00'));
    }

    public function test_ledger_keeps_zero_interest_and_zero_monetary_delinquency_while_counting_days(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-16 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $summary = $this->delinquency->summarizeLoan($loan->fresh('installments'));
        $row = $summary['ledger']->first();

        $this->assertSame('0.00', $row['interest_due']);
        $this->assertSame('0.00', $row['delinquency_due']);
        $this->assertSame('1000.00', $row['principal_due']);
        $this->assertSame(6, $row['days_overdue']);
        $this->assertSame('overdue', $row['settlement']);
        $this->assertSame('En mora', $row['settlement_label']);
        $this->assertSame('6 días', $row['mora_label']);
        $this->assertFalse($summary['monetary_delinquency_enabled']);
        $this->assertSame(6, $summary['current_days']);
        $this->assertSame('2026-08-10', $summary['oldest_due_on']->toDateString());
    }

    public function test_paid_history_lists_receipts_and_component_allocations(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-16 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $payment = $this->applyPayment($loan, $installment, '400.00');

        $summary = $this->delinquency->summarizeLoan($loan->fresh());
        $entry = $summary['paid_history']->first();

        $this->assertSame('payment', $entry['source']);
        $this->assertSame($payment->receipt_number, $entry['title']);
        $this->assertSame('400.00', (string) $entry['amount']);
        $this->assertSame(1, $entry['allocations'][0]['installment']);
        $this->assertSame('principal', $entry['allocations'][0]['component']);
        $this->assertSame('Principal', $entry['allocations'][0]['component_label']);
        $this->assertSame('400.00', (string) $entry['allocations'][0]['amount']);
        $this->assertSame('600.00', $summary['ledger']->first()['outstanding_amount']);
        $this->assertSame('overdue', $summary['ledger']->first()['settlement']);
        $this->assertCount(1, $summary['ledger']->first()['history']);
        $this->assertSame($payment->receipt_number, $summary['ledger']->first()['history'][0]['title']);
    }

    public function test_full_payment_on_or_before_due_date_is_on_time_without_mora(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-20', '1000.00');
        $this->travelTo($this->at('2026-08-18 10:00:00'));
        $this->applyPayment($loan, $installment, '1000.00');

        $row = $this->delinquency->summarizeLoan($loan->fresh())['ledger']->first();

        $this->assertSame('on_time', $row['settlement']);
        $this->assertSame('A tiempo', $row['settlement_label']);
        $this->assertSame('—', $row['mora_label']);
        $this->assertSame(0, $row['days_overdue']);
    }

    public function test_full_payment_after_due_date_is_late_and_stops_active_mora(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-16 10:00:00'));
        $this->delinquency->recalculateLoan($loan->fresh('installments'));
        $this->applyPayment($loan, $installment, '1000.00');

        $row = $this->delinquency->summarizeLoan($loan->fresh())['ledger']->first();

        $this->assertSame('late', $row['settlement']);
        $this->assertSame('Pagada tarde', $row['settlement_label']);
        $this->assertSame('—', $row['mora_label']);
        $this->assertFalse($row['history'][0]['on_time']);
    }

    public function test_recalculate_requires_a_daily_rate_and_applies_mora_amount_per_overdue_installment(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->addInstallment($loan, 2, '2026-08-20', '800.00');
        $this->travelTo($this->at('2026-08-16 10:00:00'));

        $this->from(route('loans.show', $loan))
            ->post(route('loans.delinquency.recalculate', $loan), [])
            ->assertRedirect(route('loans.show', $loan))
            ->assertSessionHasErrors('daily_rate');

        $this->from(route('loans.show', $loan))
            ->post(route('loans.delinquency.recalculate', $loan), ['daily_rate' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $loan = $loan->fresh('installments');
        $first = $loan->installments->firstWhere('number', 1);
        $second = $loan->installments->firstWhere('number', 2);

        $this->assertSame('1.000000', (string) $loan->delinquency_daily_rate);
        $this->assertSame('60.00', (string) $first->delinquency_due);
        $this->assertSame('0.00', (string) $second->delinquency_due);
        $this->assertSame('60.00', (string) $loan->delinquency_balance);
        $this->assertSame('1060.00', $first->outstandingAmount());
        $this->assertDatabaseHas('delinquency_accruals', [
            'installment_id' => $first->id,
            'method' => 'daily_percentage',
            'days_overdue' => 6,
            'amount' => '60.00',
            'status' => 'posted',
        ]);
    }

    public function test_recalculate_with_new_daily_rate_replaces_the_mora_amount(): void
    {
        $loan = $this->loan();
        $installment = $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-16 10:00:00'));

        $this->delinquency->recalculateLoan($loan->fresh('installments'), now(), ['daily_rate' => '1', 'trigger' => 'manual', 'actor_id' => auth()->id()]);
        $this->delinquency->recalculateLoan($loan->fresh('installments'), now(), ['daily_rate' => '2', 'trigger' => 'manual', 'actor_id' => auth()->id()]);

        $this->assertSame('120.00', (string) $installment->fresh()->delinquency_due);
        $this->assertSame('2.000000', (string) $loan->fresh()->delinquency_daily_rate);
        $this->assertSame(1, DelinquencyAccrual::query()->where('installment_id', $installment->id)->where('status', 'reversed')->count());
        $this->assertSame('120.00', (string) DelinquencyAccrual::query()->where('installment_id', $installment->id)->where('status', 'posted')->whereDoesntHave('reversal')->value('amount'));
    }

    public function test_cases_cannot_be_deleted(): void
    {
        $loan = $this->loan();
        $this->addInstallment($loan, 1, '2026-08-10', '1000.00');
        $this->travelTo($this->at('2026-08-12 10:00:00'));
        $case = $this->delinquency->recalculateLoan($loan->fresh('installments'));

        $this->expectException(\LogicException::class);
        $case->delete();
    }

    private function loan(): Loan
    {
        return Loan::query()->firstOrFail();
    }

    private function secondLoan(Client $client): Loan
    {
        $application = CreditApplication::create([
            'number' => 'SOL-MORA-2',
            'client_id' => $client->id,
            'seller_id' => SellerProfile::firstOrFail()->id,
            'credit_product_id' => $this->loan()->application->credit_product_id,
            'status' => 'disbursed',
            'requested_amount' => '7000.00',
            'approved_amount' => '7000.00',
            'currency' => 'NIO',
            'purpose' => 'Capital de trabajo',
            'term' => 10,
            'payment_frequency' => 'weekly',
            'economic_snapshot' => [],
        ]);

        return Loan::create([
            'number' => 'PRE-MORA-2',
            'credit_application_id' => $application->id,
            'client_id' => $client->id,
            'seller_id' => SellerProfile::firstOrFail()->id,
            'status' => 'active',
            'currency' => 'NIO',
            'principal' => '7000.00',
            'principal_balance' => '7000.00',
            'interest_balance' => '0.00',
            'fee_balance' => '0.00',
            'approved_terms' => ['term' => 10, 'frequency' => 'weekly'],
            'disbursed_at' => '2026-07-01',
        ]);
    }

    private function addInstallment(Loan $loan, int $number, string $dueDate, string $principal, string $paid = '0.00', string $status = 'pending'): LoanInstallment
    {
        return LoanInstallment::create([
            'loan_id' => $loan->id,
            'number' => $number,
            'due_date' => $dueDate,
            'principal_due' => $principal,
            'interest_due' => '0.00',
            'fees_due' => '0.00',
            'delinquency_due' => '0.00',
            'principal_paid' => $paid,
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
            'delinquency_paid' => '0.00',
            'paid_amount' => $paid,
            'status' => $status,
        ]);
    }

    private function applyPayment(Loan $loan, LoanInstallment $installment, string $amount): Payment
    {
        $paid = bcadd($installment->fresh()->amountPaid(), $amount, 2);
        $installment->update(['principal_paid' => $paid, 'paid_amount' => $paid]);

        $payment = Payment::create([
            'idempotency_key' => (string) Str::uuid(),
            'receipt_number' => 'REC-'.Str::upper(Str::random(6)),
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'collector_id' => User::firstOrFail()->id,
            'received_at' => now(),
            'amount' => $amount,
            'currency' => 'NIO',
            'payment_method' => 'cash',
            'previous_balance' => $loan->outstanding_balance,
            'new_balance' => bcsub($loan->outstanding_balance, $amount, 2),
            'status' => 'applied',
            'created_by' => User::firstOrFail()->id,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'installment_id' => $installment->id,
            'component' => 'principal',
            'amount' => $amount,
            'application_order' => 1,
            'policy_snapshot' => ['source' => 'test'],
        ]);

        return $payment;
    }

    private function reversePayment(Payment $payment, LoanInstallment $installment): PaymentReversal
    {
        $installment->update(['principal_paid' => '0.00', 'paid_amount' => '0.00']);

        return PaymentReversal::create([
            'payment_id' => $payment->id,
            'number' => 'REV-001',
            'reason' => 'Anulación de prueba',
            'authorized_by' => User::firstOrFail()->id,
            'created_by' => User::firstOrFail()->id,
            'reversed_at' => now(),
        ]);
    }

    private function at(string $datetime): Carbon
    {
        return Carbon::parse($datetime, 'America/Managua');
    }
}
