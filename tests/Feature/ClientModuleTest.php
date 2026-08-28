<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientModuleTest extends TestCase
{
    use RefreshDatabase;

    private function identity(string $birthDate, string $serial = '0001', string $municipality = '441'): string
    {
        $date = date('dmy', strtotime($birthDate));
        $number = $municipality.$date.$serial;
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXY';

        return $municipality.'-'.$date.'-'.$serial.$letters[(int) $number % 23];
    }

    private function clientData(SellerProfile $seller, string $birthDate = '1990-01-01', string $serial = '0001'): array
    {
        return ['full_name' => 'Cliente Nuevo', 'identity_number' => $this->identity($birthDate, $serial), 'birth_date' => $birthDate, 'phone' => '8888-0000', 'department' => 'Estelí', 'municipality' => 'Estelí', 'neighborhood' => 'El Calvario', 'address' => 'Estelí centro', 'estimated_income' => '15000.00', 'estimated_expenses' => '7000.00', 'status' => 'active', 'seller_id' => $seller->id];
    }

    private function seller(string $name = 'Vendedor Uno'): SellerProfile
    {
        $user = User::factory()->create(['name' => $name]);
        $branch = Branch::create(['code' => fake()->unique()->numerify('S-###'), 'name' => 'Central']);
        $zone = Zone::create(['branch_id' => $branch->id, 'code' => fake()->unique()->numerify('Z-###'), 'name' => 'Centro']);

        return SellerProfile::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'zone_id' => $zone->id, 'code' => fake()->unique()->numerify('V-###'), 'status' => 'active']);
    }

    public function test_create_form_loads_active_sellers_from_database(): void
    {
        $activeSeller = $this->seller('Vendedor Disponible');
        $inactiveSeller = $this->seller('Vendedor Inactivo');
        $inactiveSeller->update(['status' => 'inactive']);

        $this->get(route('clients.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Form')
                ->has('sellers', 1)
                ->where('sellers.0.user.name', 'Vendedor Disponible')
                ->where('editing', false));
    }

    public function test_textual_fields_do_not_load_predefined_comboboxes(): void
    {
        $this->seller();
        Client::create(['code' => 'CLI-SUG-001', 'full_name' => 'Cliente Referencia', 'birth_date' => '1990-01-01', 'address' => 'Estelí', 'economic_activity' => 'Venta de ropa', 'workplace' => 'Mercado Alfredo Lazo', 'job_position' => 'Comerciante', 'status' => 'active']);

        $this->get(route('clients.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Form')
            ->missing('suggestions')
            ->missing('clients'));
    }

    public function test_birth_date_is_autocompleted_from_identity_number(): void
    {
        $this->seller();

        $this->get(route('clients.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Clients/Form'));
    }

    public function test_identity_number_uses_an_automatic_input_mask(): void
    {
        $this->seller();

        $this->get(route('clients.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Clients/Form'));
    }

    public function test_identity_mask_allows_deleting_across_separators(): void
    {
        $this->seller();

        $this->get(route('clients.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Clients/Form'));
    }

    public function test_seller_combobox_endpoint_returns_limited_safe_json(): void
    {
        $seller = $this->seller('Vendedor Dinámico');

        $this->getJson(route('clients.seller-options', ['q' => 'Din']))
            ->assertOk()->assertJsonPath('data.0.id', $seller->id)
            ->assertJsonPath('data.0.label', 'Vendedor Dinámico')
            ->assertJsonMissingPath('data.0.identity_number');
        $this->getJson(route('clients.seller-options', ['q' => 'D']))->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_create_form_has_a_review_modal_before_visual_submission(): void
    {
        $this->seller();
        $this->get(route('clients.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Form')
            ->where('endpoints.save', route('clients.store')));
    }

    public function test_client_can_be_created_with_automatic_code_and_portfolio_assignment(): void
    {
        $admin = User::factory()->create();
        $seller = $this->seller();
        $response = $this->actingAs($admin)->post(route('clients.store'), $this->clientData($seller));
        $client = Client::firstOrFail();
        $response->assertRedirect(route('clients.show', $client));
        $this->assertSame('CLI-000001', $client->code);
        $this->assertSame($seller->id, $client->activeAssignment->seller_id);
    }

    public function test_duplicate_identity_is_rejected_and_phone_requires_confirmation(): void
    {
        $admin = User::factory()->create();
        $seller = $this->seller();
        $existingIdentity = $this->identity('1990-01-01', '0001');
        Client::create(['code' => 'CLI-999999', 'full_name' => 'Existente', 'identity_number' => $existingIdentity, 'birth_date' => '1990-01-01', 'phone' => '8888', 'address' => 'Estelí', 'status' => 'active']);
        $base = $this->clientData($seller, '1990-01-02', '0002') + ['full_name' => 'Duplicado'];
        $this->actingAs($admin)->post(route('clients.store'), array_merge($base, ['identity_number' => $existingIdentity, 'birth_date' => '1990-01-01']))->assertSessionHasErrors('identity_number');
        $this->actingAs($admin)->post(route('clients.store'), array_merge($base, ['phone' => '8888']))->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post(route('clients.store'), array_merge($base, ['phone' => '8888', 'confirm_duplicate' => 1]))->assertSessionHasNoErrors();
    }

    public function test_portfolio_transfer_preserves_history(): void
    {
        $admin = User::factory()->create();
        $first = $this->seller('Primero');
        $second = $this->seller('Segundo');
        $this->actingAs($admin)->post(route('clients.store'), $this->clientData($first) + ['full_name' => 'Cliente']);
        $client = Client::firstOrFail();
        $this->actingAs($admin)->post(route('clients.transfer', $client), ['seller_id' => $second->id, 'reason' => 'Redistribución territorial'])->assertSessionHasNoErrors();
        $this->assertCount(2, $client->fresh()->portfolioAssignments);
        $this->assertSame($second->id, $client->fresh()->activeAssignment->seller_id);
        $this->assertSame($first->id, $client->fresh()->activeAssignment->previous_seller_id);
        $this->assertSame($admin->id, $client->fresh()->activeAssignment->assigned_by);
        $this->get(route('clients.show', $client))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Show')
            ->has('cycles')
            ->has('client.portfolio_assignments', 2)
            ->where('client.portfolio_assignments.0.seller_name', 'Segundo')
            ->where('client.portfolio_assignments.1.seller_name', 'Primero')
            ->where('client.portfolio_assignments.0.reason', 'Redistribución territorial'));
    }

    public function test_identity_control_letter_and_birth_date_must_be_valid(): void
    {
        $seller = $this->seller();
        $valid = $this->clientData($seller);

        $this->post(route('clients.store'), array_merge($valid, ['identity_number' => substr($valid['identity_number'], 0, -1).'Z']))->assertSessionHasErrors('identity_number');
        $this->post(route('clients.store'), array_merge($valid, ['birth_date' => '1990-01-02']))->assertSessionHasErrors('birth_date');
    }

    public function test_municipality_and_neighborhood_must_match_their_parent_selection(): void
    {
        $seller = $this->seller();
        $valid = $this->clientData($seller);

        $this->post(route('clients.store'), array_merge($valid, ['municipality' => 'León']))->assertSessionHasErrors('municipality');
        $this->post(route('clients.store'), array_merge($valid, ['neighborhood' => 'Barrio inexistente']))->assertSessionHasErrors('neighborhood');
    }

    public function test_economic_profile_and_assets_are_saved_without_requiring_a_guarantor(): void
    {
        $seller = $this->seller();
        $data = array_merge($this->clientData($seller), [
            'workplace' => 'Pulpería Central', 'job_position' => 'Propietario', 'other_income' => '2000.00', 'dependents' => 2,
            'assets' => [['type' => 'jewelry', 'description' => 'Cadena de oro', 'estimated_value' => '5000.00', 'ownership_status' => 'owned']],
        ]);

        $this->post(route('clients.store'), $data)->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $this->assertSame('Cadena de oro', $client->assets()->firstOrFail()->description);
        $this->assertDatabaseCount('client_guarantors', 0);

        $this->put(route('clients.update', $client), array_merge($data, ['assets' => [['type' => 'vehicle', 'description' => 'Motocicleta', 'ownership_status' => 'owned']]]))->assertSessionHasNoErrors();
        $this->assertSame('Motocicleta', $client->assets()->firstOrFail()->description);
    }

    public function test_client_directory_exposes_intuitive_modal_workflows(): void
    {
        $this->get(route('clients.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->has('clients.data')
            ->has('filters')
            ->has('board.briefing.title')
            ->has('board.stats.total')
            ->has('board.mix', 3)
            ->has('board.growth.points', 12)
            ->where('endpoints.create', route('clients.create')));
    }

    public function test_client_directory_board_breaks_down_credit_situation(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $inArrears = Client::query()->where('code', 'CLI-000001')->firstOrFail();
        $inactive = Client::query()->where('code', 'CLI-000003')->firstOrFail();
        $inArrears->loans()->update(['status' => 'delinquent']);
        $inactive->loans()->update(['status' => 'paid', 'principal_balance' => 0, 'interest_balance' => 0, 'fee_balance' => 0, 'delinquency_balance' => 0]);
        $inactive->update(['status' => 'inactive']);

        $this->get(route('clients.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->where('board.stats.total', 3)
            ->where('board.stats.active', 2)
            ->where('board.stats.inactive', 1)
            ->where('board.stats.in_arrears', 1)
            ->where('board.mix.0.value', 2)
            ->where('board.mix.1.value', 1)
            ->has('board.growth.points', 12)
            ->where('board.growth.points.11.total', 3)
            ->where('board.growth.delta', 3));
    }

    public function test_client_directory_growth_accumulates_new_clients_by_month(): void
    {
        $seller = $this->seller();
        $this->post(route('clients.store'), array_merge($this->clientData($seller, '1990-01-01', '0001'), ['full_name' => 'Cliente Enero', 'phone' => '8888-1101']))->assertSessionHasNoErrors();
        $this->post(route('clients.store'), array_merge($this->clientData($seller, '1990-01-02', '0002'), ['full_name' => 'Cliente Marzo', 'phone' => '8888-1102']))->assertSessionHasNoErrors();
        Client::query()->where('full_name', 'Cliente Enero')->update(['created_at' => now()->startOfMonth()->subMonths(2)]);
        Client::query()->where('full_name', 'Cliente Marzo')->update(['created_at' => now()->startOfMonth()]);

        $this->get(route('clients.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('board.growth.points.9.total', 1)
            ->where('board.growth.points.10.total', 1)
            ->where('board.growth.points.11.total', 2)
            ->where('board.growth.added', 1)
            ->where('board.growth.delta', 1));
    }

    public function test_client_directory_supports_quick_search_and_optional_seller_filter(): void
    {
        $first = $this->seller('Vendedor Buscado');
        $second = $this->seller('Vendedor Distinto');
        $this->post(route('clients.store'), array_merge($this->clientData($first), ['full_name' => 'María Encontrada']));
        $this->post(route('clients.store'), array_merge($this->clientData($second, '1990-01-02', '0002'), ['full_name' => 'Carlos Oculto']));

        $this->get(route('clients.index', ['search' => 'María', 'seller' => $first->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 1)
                ->where('clients.data.0.full_name', 'María Encontrada')
                ->where('clients.data.0.active_assignment.seller.user.name', 'Vendedor Buscado'));
    }

    public function test_create_rejects_missing_required_fields_and_incomplete_coordinates(): void
    {
        $seller = $this->seller();
        $valid = $this->clientData($seller);

        $this->post(route('clients.store'), collect($valid)->except('full_name', 'seller_id')->all())
            ->assertSessionHasErrors(['full_name', 'seller_id']);
        $this->post(route('clients.store'), array_merge($valid, ['latitude' => 13.09]))
            ->assertSessionHasErrors('longitude');
        $this->post(route('clients.store'), array_merge($valid, ['longitude' => -86.35]))
            ->assertSessionHasErrors('latitude');
    }

    public function test_seller_without_prospecting_cannot_be_assigned(): void
    {
        $seller = $this->seller();
        $seller->update(['capabilities' => ['collections']]);

        $this->post(route('clients.store'), $this->clientData($seller))->assertSessionHasErrors('seller_id');
        $this->getJson(route('clients.seller-options', ['q' => 'Vendedor']))
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_edit_form_exposes_calendar_birth_date_and_update_ignores_seller_change(): void
    {
        $first = $this->seller('Original');
        $second = $this->seller('Intruso');
        $this->post(route('clients.store'), $this->clientData($first))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();

        $this->get(route('clients.edit', $client))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Form')
            ->where('editing', true)
            ->where('client.birth_date', '1990-01-01')
            ->where('endpoints.save', route('clients.update', $client)));

        $this->put(route('clients.update', $client), array_merge($this->clientData($second), [
            'full_name' => 'Cliente Actualizado',
            'seller_id' => $second->id,
        ]))->assertSessionHasNoErrors();

        $client = $client->fresh();
        $this->assertSame('Cliente Actualizado', $client->full_name);
        $this->assertSame($first->id, $client->activeAssignment->seller_id);
    }

    public function test_identity_remains_unique_on_update_except_for_the_same_client(): void
    {
        $seller = $this->seller();
        $this->post(route('clients.store'), $this->clientData($seller, '1990-01-01', '0001'))->assertSessionHasNoErrors();
        $this->post(route('clients.store'), array_merge($this->clientData($seller, '1990-01-02', '0002'), ['phone' => '8888-0002']))->assertSessionHasNoErrors();
        $first = Client::query()->where('identity_number', $this->identity('1990-01-01', '0001'))->firstOrFail();
        $second = Client::query()->where('identity_number', $this->identity('1990-01-02', '0002'))->firstOrFail();

        $this->put(route('clients.update', $first), $this->clientData($seller, '1990-01-01', '0001'))->assertSessionHasNoErrors();
        $this->put(route('clients.update', $second), array_merge($this->clientData($seller, '1990-01-01', '0001'), ['phone' => '8888-0002']))
            ->assertSessionHasErrors('identity_number');
    }

    public function test_empty_asset_rows_are_ignored_and_assets_can_be_cleared(): void
    {
        $seller = $this->seller();
        $data = array_merge($this->clientData($seller), [
            'assets' => [
                ['type' => 'jewelry', 'description' => 'Anillo', 'estimated_value' => '100.00', 'ownership_status' => 'owned'],
                ['type' => 'vehicle', 'description' => '', 'ownership_status' => 'owned'],
            ],
        ]);
        $this->post(route('clients.store'), $data)->assertSessionHasErrors('assets.1.description');

        $this->post(route('clients.store'), array_merge($data, [
            'assets' => [['type' => 'jewelry', 'description' => 'Anillo', 'estimated_value' => '100.00', 'ownership_status' => 'owned']],
        ]))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $this->assertDatabaseCount('client_assets', 1);

        $this->put(route('clients.update', $client), array_merge($this->clientData($seller), ['assets' => []]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('client_assets', 0);
    }

    public function test_client_is_inactivated_without_deleting_history(): void
    {
        $seller = $this->seller();
        $this->post(route('clients.store'), $this->clientData($seller))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();

        $this->from(route('clients.show', $client))
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => 'inactive']);
        $this->assertNull($client->fresh()->deleted_at);
        $this->assertNotNull($client->fresh()->activeAssignment);
    }

    public function test_transfer_to_the_same_seller_is_rejected(): void
    {
        $seller = $this->seller();
        $this->post(route('clients.store'), $this->clientData($seller))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();

        $this->post(route('clients.transfer', $client), ['seller_id' => $seller->id, 'reason' => 'Sin cambio'])
            ->assertSessionHasErrors('seller_id');
        $this->assertCount(1, $client->fresh()->portfolioAssignments);
    }

    public function test_portfolio_history_lists_the_newest_assignment_first_when_timestamps_differ(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $first = $this->seller('Primero');
        $second = $this->seller('Segundo');
        $this->post(route('clients.store'), $this->clientData($first))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();

        Carbon::setTestNow('2026-03-01 10:00:05');
        $this->post(route('clients.transfer', $client), ['seller_id' => $second->id, 'reason' => 'Cambio de zona'])->assertSessionHasNoErrors();

        $this->get(route('clients.show', $client))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('client.portfolio_assignments.0.seller_name', 'Segundo')
            ->where('client.portfolio_assignments.1.seller_name', 'Primero'));

        Carbon::setTestNow();
    }

    public function test_show_builds_financial_cycles_in_solicitud_desembolso_cuota_order(): void
    {
        $seller = $this->seller();
        $this->post(route('clients.store'), $this->clientData($seller))->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $product = CreditProduct::create([
            'code' => 'MICRO-TEST',
            'name' => 'Microcrédito de prueba',
            'currency' => 'NIO',
            'allowed_frequencies' => ['weekly'],
            'allowed_interest_methods' => ['flat'],
            'payment_allocation_order' => ['interest', 'principal'],
            'is_active' => true,
        ]);
        $draft = CreditApplication::create([
            'number' => 'SOL-DRAFT',
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'credit_product_id' => $product->id,
            'status' => 'submitted',
            'requested_amount' => '3000.00',
            'currency' => 'NIO',
            'purpose' => 'Inventario',
            'term' => 4,
            'payment_frequency' => 'weekly',
            'applied_on' => '2026-01-10',
        ]);
        $disbursed = CreditApplication::create([
            'number' => 'SOL-LIVE',
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'credit_product_id' => $product->id,
            'status' => 'disbursed',
            'requested_amount' => '8000.00',
            'approved_amount' => '8000.00',
            'currency' => 'NIO',
            'purpose' => 'Capital',
            'term' => 2,
            'payment_frequency' => 'weekly',
            'applied_on' => '2026-02-01',
        ]);
        $loan = Loan::create([
            'number' => 'PRE-LIVE',
            'credit_application_id' => $disbursed->id,
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'status' => 'active',
            'currency' => 'NIO',
            'principal' => '8000.00',
            'principal_balance' => '8000.00',
            'approved_terms' => ['term' => 2],
            'disbursed_at' => today()->subWeek(),
        ]);
        LoanInstallment::create([
            'loan_id' => $loan->id, 'number' => 2, 'due_date' => today()->addWeeks(2),
            'principal_due' => '4000.00', 'interest_due' => '100.00', 'fees_due' => '0.00',
            'delinquency_due' => '0.00', 'principal_paid' => '0.00', 'interest_paid' => '0.00',
            'fees_paid' => '0.00', 'delinquency_paid' => '0.00', 'paid_amount' => '0.00', 'status' => 'pending',
        ]);
        LoanInstallment::create([
            'loan_id' => $loan->id, 'number' => 1, 'due_date' => today()->addWeek(),
            'principal_due' => '4000.00', 'interest_due' => '100.00', 'fees_due' => '0.00',
            'delinquency_due' => '0.00', 'principal_paid' => '0.00', 'interest_paid' => '0.00',
            'fees_paid' => '0.00', 'delinquency_paid' => '0.00', 'paid_amount' => '0.00', 'status' => 'pending',
        ]);

        $this->get(route('clients.show', $client))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Show')
            ->where('client.birth_date', '1990-01-01')
            ->where('endpoints.index', route('clients.index'))
            ->has('cycles', 2)
            ->where('cycles.0.title', $draft->number)
            ->has('cycles.0.rows', 1)
            ->where('cycles.0.rows.0.kind', 'solicitud')
            ->where('cycles.0.rows.0.status', 'Enviada')
            ->where('cycles.1.title', $disbursed->number)
            ->where('cycles.1.product', 'Microcrédito de prueba')
            ->has('cycles.1.rows', 4)
            ->where('cycles.1.rows.0.kind', 'solicitud')
            ->where('cycles.1.rows.1.kind', 'desembolso')
            ->where('cycles.1.rows.1.amount', '8000.00')
            ->where('cycles.1.rows.2.kind', 'cuota')
            ->where('cycles.1.rows.2.label', 'Cuota 1')
            ->where('cycles.1.rows.3.label', 'Cuota 2')
            ->where('cycles.1.rows.2.url', route('loans.show', $loan))
            ->where('delinquency.in_arrears', false));
    }

    public function test_directory_filters_by_status_and_keeps_selected_client_off_the_current_page(): void
    {
        $seller = $this->seller();
        foreach (range(1, 13) as $index) {
            $serial = str_pad((string) $index, 4, '0', STR_PAD_LEFT);
            $this->post(route('clients.store'), array_merge($this->clientData($seller, '1990-01-01', $serial), [
                'full_name' => 'Cliente '.$serial,
                'phone' => '8888-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            ]))->assertSessionHasNoErrors();
        }
        $oldest = Client::query()->orderBy('id')->firstOrFail();
        $blocked = Client::query()->orderByDesc('id')->firstOrFail();
        $blocked->update(['status' => 'blocked']);

        $this->get(route('clients.index', ['status' => 'blocked']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('clients.data', 1)
                ->where('clients.data.0.status', 'blocked')
                ->where('board.stats.blocked', 1)
                ->where('board.mix.2.value', 1));

        $this->get(route('clients.index', ['page' => 2, 'client' => $oldest->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedClient.id', $oldest->id)
                ->where('selectedClient.full_name', $oldest->full_name));
    }
}
