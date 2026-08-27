<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('name="seller_id"', false)
            ->assertSee('Vendedor Disponible')
            ->assertDontSee('Vendedor Inactivo');
    }

    public function test_textual_fields_do_not_load_predefined_comboboxes(): void
    {
        $this->seller();
        Client::create(['code' => 'CLI-SUG-001', 'full_name' => 'Cliente Referencia', 'birth_date' => '1990-01-01', 'address' => 'Estelí', 'economic_activity' => 'Venta de ropa', 'workplace' => 'Mercado Alfredo Lazo', 'job_position' => 'Comerciante', 'status' => 'active']);

        $this->get(route('clients.create'))->assertOk()
            ->assertDontSee('client-autocomplete-list', false)
            ->assertDontSee('Cliente Referencia')->assertDontSee('Venta de ropa')->assertDontSee('Mercado Alfredo Lazo')->assertDontSee('Comerciante')
            ->assertDontSee('dataset.searchableCombobox', false);
    }

    public function test_birth_date_is_autocompleted_from_identity_number(): void
    {
        $this->seller();

        $this->get(route('clients.create'))->assertOk()
            ->assertSee('fillBirthDateFromIdentity', false)
            ->assertSee('digits.slice(3,9)', false)
            ->assertSee('id="birth-date"', false);
    }

    public function test_identity_number_uses_an_automatic_input_mask(): void
    {
        $this->seller();

        $this->get(route('clients.create'))->assertOk()
            ->assertSee('formatIdentityNumber', false)
            ->assertSee('digits.slice(0,3)', false)
            ->assertSee('digits.slice(3,9)', false)
            ->assertSee('digits.slice(9,13)', false);
    }

    public function test_identity_mask_allows_deleting_across_separators(): void
    {
        $this->seller();

        $this->get(route('clients.create'))->assertOk()
            ->assertSee("event.key==='Backspace'", false)
            ->assertSee("event.key==='Delete'", false)
            ->assertSee('setRangeText', false);
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
        $this->get(route('clients.create'))->assertOk()->assertSee('client-review-modal', false)->assertSee('Revisar y registrar')->assertSee('Confirmar y guardar');
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
        $this->get(route('clients.show', $client))->assertOk()
            ->assertSee('Primero')
            ->assertSee('Segundo')
            ->assertSee('Redistribución territorial');
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
            ->where('endpoints.create', route('clients.create')));
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
}
