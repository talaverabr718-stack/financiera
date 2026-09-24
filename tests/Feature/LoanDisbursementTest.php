<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoanDisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_application_can_be_disbursed_once(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $client = Client::firstOrFail();
        $this->settleOpenLoans($client);
        $application = $this->approvedApplication();
        $key = (string) Str::uuid();

        $response = $this->actingAs($user)->post(route('applications.disburse', $application), [
            'idempotency_key' => $key,
            'disbursed_at' => today()->format('Y-m-d'),
            'payment_method' => 'cash',
            'reference' => 'REC-001',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $loan = Loan::where('credit_application_id', $application->id)->firstOrFail();
        $response->assertRedirect(route('loans.show', $loan));
        $this->assertSame('disbursed', $application->fresh()->status);
        $this->assertSame('active', $loan->status);
        $this->assertSame('OPEN', $loan->open_guard);
        $this->assertSame('5000.00', $loan->principal_balance);
        $this->assertDatabaseHas('loan_disbursements', ['idempotency_key' => $key, 'loan_id' => $loan->id, 'amount' => '5000.00']);

        $this->actingAs($user)->post(route('applications.disburse', $application), [
            'idempotency_key' => $key,
            'disbursed_at' => today()->format('Y-m-d'),
            'payment_method' => 'cash',
        ])->assertRedirect(route('loans.show', $loan));
        $this->assertDatabaseCount('loan_disbursements', 1);

        $this->actingAs($user)->get(route('applications.show', $application))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Applications/Show')->where('application.status', 'disbursed'));
        $this->actingAs($user)->patch(route('applications.status', $application), ['status' => 'cancelled'])
            ->assertRedirect(route('applications.show', $application))->assertSessionHas('success');
        $this->assertSame('disbursed', $application->fresh()->status);

    }

    public function test_client_with_an_open_credit_cannot_receive_a_second_disbursement(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $client = Client::firstOrFail();
        $openLoan = $client->loans()->whereIn('status', Loan::COLLECTIBLE_STATUSES)->firstOrFail();
        $application = $this->approvedApplication();

        $this->actingAs($user)->post(route('applications.disburse', $application), [
            'idempotency_key' => (string) Str::uuid(),
            'disbursed_at' => today()->format('Y-m-d'),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors([
            'disbursement' => 'Este cliente ya tiene un crédito activo o en mora. Debe finalizarlo antes de recibir otro crédito.',
        ]);

        $this->assertDatabaseMissing('loans', ['credit_application_id' => $application->id]);
        $this->assertDatabaseMissing('loan_disbursements', ['credit_application_id' => $application->id]);
        $this->assertSame('OPEN', $openLoan->fresh()->open_guard);
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_database_constraint_rejects_a_second_open_loan_for_the_same_client(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $application = $this->approvedApplication();

        $this->expectException(QueryException::class);

        Loan::create([
            'number' => 'PRE-DUPLICATE',
            'credit_application_id' => $application->id,
            'client_id' => $application->client_id,
            'seller_id' => $application->seller_id,
            'status' => 'active',
            'currency' => 'NIO',
            'principal' => '5000.00',
            'principal_balance' => '5000.00',
            'interest_balance' => '0.00',
            'fee_balance' => '0.00',
            'delinquency_balance' => '0.00',
            'approved_terms' => [],
            'disbursed_at' => today(),
        ]);
    }

    public function test_unapproved_application_cannot_be_disbursed(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $application = $this->approvedApplication();
        $application->update(['status' => 'review']);

        $this->actingAs($user)->post(route('applications.disburse', $application), [
            'idempotency_key' => (string) Str::uuid(),
            'disbursed_at' => today()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ])->assertSessionHasErrors('disbursement');

        $this->assertDatabaseMissing('loans', ['credit_application_id' => $application->id]);
    }

    private function settleOpenLoans(Client $client): void
    {
        $client->loans()->whereIn('status', Loan::COLLECTIBLE_STATUSES)->get()->each->update([
            'status' => 'paid',
            'principal_balance' => '0.00',
            'interest_balance' => '0.00',
            'fee_balance' => '0.00',
            'delinquency_balance' => '0.00',
            'closed_at' => now(),
        ]);

        $this->assertFalse($client->fresh()->hasOpenCredit());
        $this->assertNull($client->loans()->firstOrFail()->open_guard);
    }

    private function approvedApplication(): CreditApplication
    {
        return CreditApplication::create([
            'number' => 'SOL-DES-TEST',
            'client_id' => Client::firstOrFail()->id,
            'seller_id' => SellerProfile::firstOrFail()->id,
            'credit_product_id' => CreditProduct::firstOrFail()->id,
            'status' => 'approved',
            'requested_amount' => '5000.00',
            'approved_amount' => '5000.00',
            'currency' => 'NIO',
            'purpose' => 'Capital de trabajo',
            'term' => 10,
            'payment_frequency' => 'weekly',
            'interest_rate' => '3.000000',
            'interest_method' => 'flat',
            'proposed_first_payment_date' => today()->addWeek(),
        ]);
    }
}
