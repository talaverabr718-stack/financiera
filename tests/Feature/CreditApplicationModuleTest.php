<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditApplicationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_can_be_created_edited_and_approved(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $payload = ['client_id' => Client::firstOrFail()->id, 'seller_id' => SellerProfile::firstOrFail()->id, 'credit_product_id' => CreditProduct::firstOrFail()->id, 'requested_amount' => '10000.00', 'currency' => 'NIO', 'purpose' => 'Capital de trabajo', 'term' => 12, 'payment_frequency' => 'weekly', 'status' => 'draft'];

        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasNoErrors();
        $application = CreditApplication::latest('id')->firstOrFail();
        $this->assertStringStartsWith('SOL-', $application->number);

        $this->actingAs($user)->put(route('applications.update', $application), array_merge($payload, ['purpose' => 'Compra de inventario', 'status' => 'review']))->assertSessionHasNoErrors();
        $this->actingAs($user)->patch(route('applications.status', $application), ['status' => 'approved', 'approved_amount' => '9000.00'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('9000.00', $application->fresh()->approved_amount);
        $this->assertNotNull($application->fresh()->decided_at);
        $this->assertNotNull($application->fresh()->approved_at);
        $this->assertSame($application->fresh()->approved_at->copy()->addWeeks(12)->toDateString(), $application->fresh()->estimated_last_payment_date->toDateString());
        $this->get(route('applications.show', $application))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Show')
            ->where('application.approved_amount', '9000.00')
            ->where('application.estimated_last_payment_date', $application->fresh()->estimated_last_payment_date->toDateString()));
    }

    public function test_sequence_skips_numbers_that_already_exist(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        DB::table('document_sequences')->where('key', 'credit_application')->update(['next_number' => 1]);
        $payload = ['client_id' => Client::firstOrFail()->id, 'seller_id' => SellerProfile::firstOrFail()->id, 'credit_product_id' => CreditProduct::firstOrFail()->id, 'requested_amount' => '5000.00', 'currency' => 'NIO', 'purpose' => 'Inventario', 'term' => 9, 'payment_frequency' => 'daily', 'status' => 'draft'];

        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('credit_applications', ['number' => 'SOL-000004', 'purpose' => 'Inventario']);
    }

    public function test_financial_product_editor_renders_its_four_allocation_positions(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $product = CreditProduct::firstOrFail();

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Mora y aplicación de pagos')
            ->assertSeeInOrder(['1°', '2°', '3°', '4°']);
    }
}
