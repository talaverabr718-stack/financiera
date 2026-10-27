<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_management_page_lists_branches_and_zones(): void
    {
        $branch = Branch::create(['code' => 'EST-01', 'name' => 'Estelí Centro', 'address' => 'Estelí', 'is_active' => true]);
        $branch->zones()->create(['code' => 'EST-CEN', 'name' => 'Centro', 'is_active' => true]);

        $this->get(route('settings.branches'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Branches')
            ->has('branches', 1)
            ->where('branches.0.name', 'Estelí Centro')
            ->where('branches.0.zones.0.name', 'Centro'));
    }

    public function test_an_administrator_can_create_and_update_a_branch(): void
    {
        $this->post(route('settings.branches.store'), [
            'code' => 'MAT-01', 'name' => 'Sucursal Matagalpa', 'address' => 'Matagalpa, Nicaragua', 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $branch = Branch::where('code', 'MAT-01')->firstOrFail();
        $this->put(route('settings.branches.update', $branch), [
            'code' => 'MAT-01', 'name' => 'Sucursal Matagalpa Centro', 'address' => 'Barrio Central, Matagalpa', 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Sucursal Matagalpa Centro', 'is_active' => true]);
    }

    public function test_branch_codes_are_unique_and_branch_status_is_reversible(): void
    {
        Branch::create(['code' => 'EST-01', 'name' => 'Estelí', 'is_active' => true]);
        $branch = Branch::create(['code' => 'JIN-01', 'name' => 'Jinotega', 'is_active' => true]);

        $this->post(route('settings.branches.store'), ['code' => 'EST-01', 'name' => 'Duplicada', 'is_active' => true])
            ->assertSessionHasErrors('code');
        $this->patch(route('settings.branches.status', $branch), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => false]);
        $this->patch(route('settings.branches.status', $branch), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => true]);
    }

    public function test_zones_can_be_created_updated_and_deactivated_only_inside_their_branch(): void
    {
        $branch = Branch::create(['code' => 'EST-01', 'name' => 'Estelí', 'is_active' => true]);
        $other = Branch::create(['code' => 'MAT-01', 'name' => 'Matagalpa', 'is_active' => true]);

        $this->post(route('settings.branches.zones.store', $branch), ['code' => 'EST-NOR', 'name' => 'Norte', 'is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $zone = Zone::where('code', 'EST-NOR')->firstOrFail();
        $this->put(route('settings.branches.zones.update', [$branch, $zone]), ['code' => 'EST-NOR', 'name' => 'Zona Norte', 'is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('settings.branches.zones.status', [$branch, $zone]), ['is_active' => false])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('zones', ['id' => $zone->id, 'branch_id' => $branch->id, 'name' => 'Zona Norte', 'is_active' => false]);
        $this->put(route('settings.branches.zones.update', [$other, $zone]), ['code' => 'EST-NOR', 'name' => 'Movida', 'is_active' => true])->assertNotFound();
    }
}
