<?php

namespace Tests\Feature;

use App\Models\Loan;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_defines_active_portfolio_and_monthly_placed_amount(): void
    {
        $this->seed(ClientModuleSeeder::class);
        Loan::orderBy('id')->firstOrFail()->update(['principal' => 12345, 'disbursed_at' => today()]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Crédito vigente otorgado a los clientes')
            ->assertSee('Total de préstamos desembolsados en '.now()->translatedFormat('F'))
            ->assertSee('C$ 12,345.00');
    }
}
