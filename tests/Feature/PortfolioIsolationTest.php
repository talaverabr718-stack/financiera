<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use App\Models\SellerProfile;
use App\Models\SystemModule;
use App\Models\SystemRole;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortfolioIsolationTest extends TestCase
{
    use RefreshDatabase;

    private SystemRole $sellerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sellerRole = SystemRole::create([
            'key' => 'portfolio_seller',
            'name' => 'Gestor de cartera',
            'is_active' => true,
        ]);

        foreach (SystemModule::query()->get() as $module) {
            DB::table('system_module_role')->insert([
                'system_role_id' => $this->sellerRole->id,
                'system_module_id' => $module->id,
                'can_view' => true,
                'can_manage' => true,
                'can_full' => $module->key !== 'settings',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_seller_only_lists_and_searches_clients_in_their_active_portfolio(): void
    {
        [$carlos, $carlosSeller] = $this->seller('Carlos López', 'CAR-001');
        [, $greyvinSeller] = $this->seller('Greyvin López', 'GRE-001');
        $carlosClient = $this->client('CLI-CAR-001', 'Cliente de Carlos', $carlosSeller, $carlos);
        $greyvinClient = $this->client('CLI-GRE-001', 'Cliente de Greyvin', $greyvinSeller, $carlos);

        $this->actingAs($carlos)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 1)
                ->where('clients.data.0.id', $carlosClient->id)
                ->has('sellers', 1)
                ->where('sellers.0.id', $carlosSeller->id));

        $this->actingAs($carlos)
            ->get(route('search', ['q' => 'Greyvin']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->has('results', 0));

        $this->actingAs($carlos)
            ->get(route('clients.index', ['seller' => $greyvinSeller->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 0));

        $this->assertNotSame($carlosClient->id, $greyvinClient->id);
    }

    public function test_seller_cannot_open_or_transfer_another_sellers_client(): void
    {
        [$carlos, $carlosSeller] = $this->seller('Carlos López', 'CAR-002');
        [, $greyvinSeller] = $this->seller('Greyvin López', 'GRE-002');
        $greyvinClient = $this->client('CLI-GRE-002', 'Cartera Greyvin', $greyvinSeller, $carlos);

        $this->actingAs($carlos)->get(route('clients.show', $greyvinClient))->assertForbidden();
        $this->actingAs($carlos)->get(route('clients.edit', $greyvinClient))->assertForbidden();

        $ownClient = $this->client('CLI-CAR-002', 'Cartera Carlos', $carlosSeller, $carlos);
        $this->actingAs($carlos)
            ->post(route('clients.transfer', $ownClient), [
                'seller_id' => $greyvinSeller->id,
                'reason' => 'Intento no autorizado',
            ])
            ->assertForbidden();
    }

    public function test_user_with_total_system_access_can_manage_every_portfolio(): void
    {
        $administratorRole = SystemRole::query()->where('key', 'administrator')->firstOrFail();
        $administrator = User::factory()->create([
            'name' => 'Administrador General',
            'system_role_id' => $administratorRole->id,
        ]);
        [$carlos, $carlosSeller] = $this->seller('Carlos López', 'CAR-003');
        [, $greyvinSeller] = $this->seller('Greyvin López', 'GRE-003');
        $this->client('CLI-CAR-003', 'Cliente Carlos', $carlosSeller, $carlos);
        $greyvinClient = $this->client('CLI-GRE-003', 'Cliente Greyvin', $greyvinSeller, $carlos);

        $this->actingAs($administrator)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 2));

        $this->actingAs($administrator)->get(route('clients.show', $greyvinClient))->assertOk();
    }

    private function seller(string $name, string $code): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'system_role_id' => $this->sellerRole->id,
        ]);
        $branch = Branch::create([
            'code' => 'S-'.$code,
            'name' => 'Sucursal '.$name,
        ]);
        $zone = Zone::create([
            'branch_id' => $branch->id,
            'code' => 'Z-'.$code,
            'name' => 'Zona '.$name,
        ]);
        $seller = SellerProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'zone_id' => $zone->id,
            'code' => $code,
            'status' => 'active',
        ]);

        return [$user, $seller];
    }

    private function client(string $code, string $name, SellerProfile $seller, User $assignedBy): Client
    {
        $client = Client::create([
            'code' => $code,
            'full_name' => $name,
            'address' => 'Estelí',
            'status' => 'active',
        ]);
        ClientPortfolioAssignment::create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'assigned_at' => now(),
            'active_guard' => 'active',
            'reason' => 'Asignación inicial',
            'assigned_by' => $assignedBy->id,
        ]);

        return $client;
    }
}
