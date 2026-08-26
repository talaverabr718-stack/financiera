<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditGuarantor;
use App\Models\CreditProduct;
use App\Models\Guarantor;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreditGuarantorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $guarantors = [], bool $requires = true): array
    {
        return [
            'client_id' => Client::firstOrFail()->id, 'seller_id' => SellerProfile::firstOrFail()->id,
            'credit_product_id' => CreditProduct::firstOrFail()->id, 'requested_amount' => '10000.00',
            'currency' => 'NIO', 'purpose' => 'Capital de trabajo', 'term' => 12,
            'payment_frequency' => 'weekly', 'status' => 'draft', 'requires_guarantor' => $requires ? '1' : '0',
            'guarantors' => $guarantors,
        ];
    }

    private function newGuarantorRow(): array
    {
        return [
            'full_name' => 'Fiador Independiente', 'identity_number' => '441-100485-0099A', 'phone' => '8777-0000',
            'relationship' => 'Hermano', 'guaranteed_amount' => '8000.00', 'guarantee_type' => 'personal',
            'workplace' => 'Comercio Central', 'monthly_income' => '18000.00', 'other_income' => '1000.00',
            'monthly_expenses' => '7000.00', 'accepted_at' => now()->format('Y-m-d H:i:s'),
            'signed_document' => UploadedFile::fake()->create('garantia.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_client_does_not_require_guarantor_and_application_owns_its_guarantees(): void
    {
        Storage::fake('local');
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();

        $this->actingAs($user)->post(route('applications.store'), $this->payload([$this->newGuarantorRow()]))->assertSessionHasNoErrors();

        $application = CreditApplication::latest('id')->firstOrFail();
        $guarantee = $application->guarantees()->firstOrFail();
        $this->assertSame('proposed', $guarantee->status);
        $this->assertCount(1, $guarantee->evaluations);
        $this->assertDatabaseCount('guarantor_documents', 1);
        $this->assertDatabaseCount('client_guarantors', 0);
    }

    public function test_reusing_guarantor_creates_new_relationship_and_evaluation(): void
    {
        Storage::fake('local');
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $this->actingAs($user)->post(route('applications.store'), $this->payload([$this->newGuarantorRow()]));
        $first = CreditApplication::latest('id')->firstOrFail();
        $guarantor = Guarantor::firstOrFail();

        $row = $this->newGuarantorRow();
        $row['guarantor_id'] = $guarantor->id;
        unset($row['full_name']);
        $this->actingAs($user)->post(route('applications.store'), $this->payload([$row]))->assertSessionHasNoErrors();

        $second = CreditApplication::latest('id')->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertCount(2, $guarantor->fresh()->guarantees);
        $this->assertDatabaseCount('guarantor_evaluations', 2);
        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()->assertSee('Fiador Independiente')->assertSee('Saldo garantizado');
    }

    public function test_guarantor_and_application_approval_are_authorized_and_ordered(): void
    {
        Storage::fake('local');
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $this->actingAs($user)->post(route('applications.store'), $this->payload([$this->newGuarantorRow()]));
        $application = CreditApplication::latest('id')->firstOrFail();
        $guarantee = CreditGuarantor::firstOrFail();

        auth()->logout();
        $this->patch(route('guarantees.decision', $guarantee), ['status' => 'approved', 'decision_reason' => 'Capacidad suficiente'])->assertForbidden();
        $this->actingAs($user)->patch(route('applications.status', $application), ['status' => 'approved', 'approved_amount' => '9000.00'])->assertSessionHasErrors('status');
        $this->actingAs($user)->patch(route('guarantees.decision', $guarantee), ['status' => 'approved', 'decision_reason' => 'Capacidad suficiente'])->assertSessionHasNoErrors();
        $this->actingAs($user)->patch(route('applications.status', $application), ['status' => 'approved', 'approved_amount' => '9000.00'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertNotNull($guarantee->fresh()->approved_by);

        $this->actingAs($user)->patch(route('guarantees.release', $guarantee), ['reason' => 'Liberación excepcional'])->assertSessionHasErrors('authorized_release');
        $this->actingAs($user)->patch(route('guarantees.release', $guarantee), ['reason' => 'Liberación excepcional', 'authorized_release' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('released', $guarantee->fresh()->status);
        $this->assertNotNull($guarantee->fresh()->released_by);
    }
}
