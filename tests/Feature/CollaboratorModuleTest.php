<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_collaborator_is_created_with_personal_data_branch_and_automatic_code(): void
    {
        [$branch] = $this->structure();
        $response = $this->post(route('collaborators.store'), [
            'name' => 'María de Campo',
            'email' => 'maria@financiera.test',
            'identity_number' => '161-010190-0001A',
            'phone' => '8888-0000',
            'branch_id' => $branch->id,
        ]);

        $collaborator = SellerProfile::firstOrFail();
        $response->assertRedirect(route('collaborators.show', $collaborator));
        $this->assertSame('COL-000001', $collaborator->code);
        $this->assertSame('María de Campo', $collaborator->full_name);
        $this->assertSame($branch->id, $collaborator->branch_id);
        $this->assertNull($collaborator->user_id);
        $this->assertDatabaseMissing('users', ['email' => 'maria@financiera.test']);
        $this->get(route('collaborators.show', $collaborator))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Collaborators/Show')->where('collaborator.display_name', 'María de Campo'));
    }

    public function test_create_form_excludes_access_capabilities_and_manual_code_fields(): void
    {
        $this->structure();

        $this->get(route('collaborators.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Collaborators/Form')
            ->missing('capabilities')
            ->missing('zones'));

        $source = file_get_contents(resource_path('js/Pages/Collaborators/Form.vue'));
        $this->assertStringNotContainsString('password', $source);
        $this->assertStringNotContainsString('capabilities', $source);
        $this->assertStringNotContainsString('v-model="form.code"', $source);
    }

    public function test_automatic_code_skips_an_existing_collaborator_code(): void
    {
        [$branch] = $this->structure();
        $user = User::factory()->create();
        SellerProfile::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'code' => 'COL-000001', 'status' => 'active']);

        $this->post(route('collaborators.store'), ['name' => 'Nuevo colaborador', 'branch_id' => $branch->id])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seller_profiles', ['full_name' => 'Nuevo colaborador', 'code' => 'COL-000002']);
    }

    public function test_inactivation_preserves_linked_user_and_profile_history(): void
    {
        [$branch, $zone] = $this->structure();
        $user = User::factory()->create();
        $collaborator = SellerProfile::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'zone_id' => $zone->id, 'code' => 'COL-003', 'status' => 'active']);
        $this->delete(route('collaborators.destroy', $collaborator))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_profiles', ['id' => $collaborator->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
