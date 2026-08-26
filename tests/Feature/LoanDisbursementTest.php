<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanDisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_application_can_be_disbursed_once(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
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
        $this->assertSame('5000.00', $loan->principal_balance);
        $this->assertDatabaseHas('loan_disbursements', ['idempotency_key' => $key, 'loan_id' => $loan->id, 'amount' => '5000.00']);

        $this->actingAs($user)->post(route('applications.disburse', $application), [
            'idempotency_key' => $key,
            'disbursed_at' => today()->format('Y-m-d'),
            'payment_method' => 'cash',
        ])->assertRedirect(route('loans.show', $loan));
        $this->assertDatabaseCount('loan_disbursements', 1);

        $this->actingAs($user)->get(route('applications.show', $application))
            ->assertOk()->assertSee('historial de la solicitud permanece protegido');
        $this->actingAs($user)->patch(route('applications.status', $application), ['status' => 'cancelled'])
            ->assertRedirect(route('applications.show', $application))->assertSessionHas('success');
        $this->assertSame('disbursed', $application->fresh()->status);

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
