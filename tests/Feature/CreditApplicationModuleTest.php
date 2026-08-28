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
        $payload = ['client_id' => Client::firstOrFail()->id, 'seller_id' => SellerProfile::firstOrFail()->id, 'credit_product_id' => CreditProduct::firstOrFail()->id, 'requested_amount' => '10000.00', 'currency' => 'NIO', 'purpose' => 'Capital de trabajo', 'installment_amount' => '1000.00', 'payment_frequency' => 'weekly', 'applied_on' => today()->subDay()->format('Y-m-d'), 'status' => 'draft'];

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Applications/Form')
                ->has('clients')
                ->has('products')
                ->has('sellers')
                ->where('editing', false)
                ->where('endpoints.save', route('applications.store')));

        $this->cancelOpenCredits();
        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasNoErrors();
        $application = CreditApplication::latest('id')->firstOrFail();
        $this->assertStringStartsWith('SOL-', $application->number);
        $this->assertSame(10, $application->term);
        $this->assertSame('1000.00', $application->installment_amount);
        $this->assertSame(today()->subDay()->toDateString(), $application->applied_on->toDateString());
        $this->assertNull($application->proposed_first_payment_date);

        $this->actingAs($user)->post(route('applications.store'), array_merge($payload, [
            'purpose' => 'Inventario con saldo',
            'installment_amount' => '3000.00',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(4, CreditApplication::query()->where('purpose', 'Inventario con saldo')->value('term'));

        $this->actingAs($user)->put(route('applications.update', $application), array_merge($payload, ['purpose' => 'Compra de inventario', 'status' => 'review']))->assertSessionHasNoErrors();
        $this->actingAs($user)->patch(route('applications.status', $application), ['status' => 'approved', 'approved_amount' => '9000.00'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('9000.00', $application->fresh()->approved_amount);
        $this->assertNotNull($application->fresh()->decided_at);
        $this->assertSame(now()->addWeek()->toDateString(), $application->fresh()->proposed_first_payment_date->toDateString());
        $this->assertNotNull($application->fresh()->approved_at);
        $this->assertSame(now()->addWeeks(10)->toDateString(), $application->fresh()->estimated_last_payment_date->toDateString());
        $this->get(route('applications.show', $application))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Applications/Show')
            ->where('application.approved_amount', '9000.00')
            ->where('application.installment_amount', $application->fresh()->installment_amount)
            ->where('application.estimated_last_payment_date', $application->fresh()->estimated_last_payment_date->toDateString()));
    }

    public function test_client_with_an_open_credit_cannot_be_selected_for_a_new_application(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $user = User::firstOrFail();
        $client = Client::firstOrFail();
        $payload = ['client_id' => $client->id, 'seller_id' => SellerProfile::firstOrFail()->id, 'credit_product_id' => CreditProduct::firstOrFail()->id, 'requested_amount' => '10000.00', 'currency' => 'NIO', 'purpose' => 'Capital de trabajo', 'installment_amount' => '1000.00', 'payment_frequency' => 'weekly', 'status' => 'draft'];

        $this->actingAs($user)->get(route('applications.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Applications/Form')
                ->missing('application.client_id')
                ->has('clients'));

        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasErrors('client_id');

        $this->cancelOpenCredits();
        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('applications.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('application.client_id', $client->id));
    }

    public function test_projected_payments_include_loan_interest(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $this->cancelOpenCredits();
        $user = User::firstOrFail();
        $product = CreditProduct::firstOrFail();
        $product->update(['default_interest_rate' => '12.000000', 'default_interest_method' => 'french']);
        $payload = ['client_id' => Client::firstOrFail()->id, 'seller_id' => SellerProfile::firstOrFail()->id, 'credit_product_id' => $product->id, 'requested_amount' => '10000.00', 'currency' => 'NIO', 'purpose' => 'Capital de trabajo', 'installment_amount' => '1000.00', 'payment_frequency' => 'monthly', 'interest_rate' => '12', 'status' => 'draft'];

        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasNoErrors();

        $application = CreditApplication::query()->where('purpose', 'Capital de trabajo')->latest('id')->firstOrFail();
        $this->assertSame(10, $application->term);
        $this->assertSame('12.000000', $application->interest_rate);
        $this->assertSame('french', $application->interest_method);
        $this->assertTrue(bccomp($application->installment_amount, '1000.00', 2) === 1);

        $this->actingAs($user)->post(route('applications.store'), array_merge($payload, [
            'purpose' => 'Demasiados pagos',
            'installment_amount' => '20.00',
        ]))->assertSessionHasErrors('term');
    }

    public function test_sequence_skips_numbers_that_already_exist(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $this->cancelOpenCredits();
        $user = User::firstOrFail();
        DB::table('document_sequences')->where('key', 'credit_application')->update(['next_number' => 1]);
        $payload = ['client_id' => Client::firstOrFail()->id, 'seller_id' => SellerProfile::firstOrFail()->id, 'credit_product_id' => CreditProduct::firstOrFail()->id, 'requested_amount' => '5000.00', 'currency' => 'NIO', 'purpose' => 'Inventario', 'term' => 9, 'payment_frequency' => 'daily', 'status' => 'draft'];

        $this->actingAs($user)->post(route('applications.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('credit_applications', ['number' => 'SOL-000004', 'purpose' => 'Inventario']);
    }

    public function test_application_product_modal_creates_a_product_with_generated_code(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $template = CreditProduct::firstOrFail();

        $response = $this->postJson(route('products.store'), [
            'quick' => true,
            'name' => 'Crédito exprés',
            'currency' => 'NIO',
            'default_interest_rate' => '5.5',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.name', 'Crédito exprés')
            ->assertJsonPath('product.currency', 'NIO');

        $code = $response->json('product.code');
        $this->assertMatchesRegularExpression('/^PRD-\d{6}$/', $code);
        $created = CreditProduct::query()->where('code', $code)->firstOrFail();
        $this->assertSame($template->payment_allocation_order, $created->payment_allocation_order);
    }

    public function test_financial_product_editor_renders_its_four_allocation_positions(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $product = CreditProduct::firstOrFail();

        $this->get(route('products.edit', $product))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Products/Form')->where('editing', true)
                ->has('product.payment_allocation_order', 4));
    }
}
