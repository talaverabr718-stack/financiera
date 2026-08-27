<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaboratorModuleTest extends TestCase
{
    use RefreshDatabase;

    private function structure(): array
    {
        $branch = Branch::create(['code' => 'EST-01', 'name' => 'Central']);
        $zone = Zone::create(['branch_id' => $branch->id, 'code' => 'EST-CEN', 'name' => 'Centro']);

        return [$branch, $zone];
    }

    public function test_collaborator_can_be_created_with_operational_capabilities(): void
    {
        [$branch, $zone] = $this->structure();
        $response = $this->post(route('collaborators.store'), [
            'name' => 'María de Campo', 'email' => 'maria@financiera.test',
            'password' => 'password', 'password_confirmation' => 'password',
            'code' => 'COL-001', 'branch_id' => $branch->id, 'zone_id' => $zone->id,
            'capabilities' => ['prospecting', 'collections'], 'status' => 'active',
        ]);
        $collaborator = SellerProfile::firstOrFail();
        $response->assertRedirect(route('collaborators.show', $collaborator));
        $this->assertSame('María de Campo', $collaborator->user->name);
        $this->assertTrue($collaborator->hasCapability('collections'));
        $this->assertFalse($collaborator->hasCapability('credit_origination'));
        $this->get(route('collaborators.show', $collaborator))->assertOk()->assertSee('María de Campo');
    }

    public function test_zone_must_belong_to_selected_branch(): void
    {
        [$branch] = $this->structure();
        $other = Branch::create(['code' => 'CON-01', 'name' => 'Condega']);
        $otherZone = Zone::create(['branch_id' => $other->id, 'code' => 'CON-N', 'name' => 'Norte']);
        $this->post(route('collaborators.store'), [
            'name' => 'Colaborador', 'email' => 'colaborador@test.com', 'password' => 'password',
            'password_confirmation' => 'password', 'code' => 'COL-002', 'branch_id' => $branch->id,
            'zone_id' => $otherZone->id, 'capabilities' => ['prospecting'], 'status' => 'active',
        ])->assertSessionHasErrors('zone_id');
    }

    public function test_inactivation_preserves_user_and_profile_history(): void
    {
        [$branch, $zone] = $this->structure();
        $user = User::factory()->create();
        $collaborator = SellerProfile::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'zone_id' => $zone->id, 'code' => 'COL-003', 'status' => 'active']);
        $this->delete(route('collaborators.destroy', $collaborator))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_profiles', ['id' => $collaborator->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
