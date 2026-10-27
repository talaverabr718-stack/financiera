<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\ProductionTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTestDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_explicit_test_data_is_safe_identifiable_and_idempotent(): void
    {
        User::factory()->create(['is_active' => true]);

        $this->seed(ProductionTestDataSeeder::class);
        $counts = [
            Branch::count(), SellerProfile::count(), Client::count(),
            CreditApplication::count(), Loan::count(),
        ];
        $this->seed(ProductionTestDataSeeder::class);

        $this->assertSame($counts, [
            Branch::count(), SellerProfile::count(), Client::count(),
            CreditApplication::count(), Loan::count(),
        ]);
        $this->assertDatabaseHas('branches', ['code' => 'DEMO-EST-01', 'is_active' => true]);
        $this->assertSame(8, Client::where('code', 'like', 'DEMO-%')->count());
        $this->assertSame(3, SellerProfile::where('code', 'like', 'DEMO-%')->count());
        $this->assertSame(5, CreditApplication::where('number', 'like', 'DEMO-%')->count());
        $this->assertSame(2, Loan::where('number', 'like', 'DEMO-%')->count());

        $product = CreditProduct::where('code', 'DEMO-CREDITO')->firstOrFail();
        $this->assertNull($product->default_interest_rate);
        $this->assertNull($product->delinquency_rate);
        $this->assertNull($product->payment_allocation_order);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_production_command_requires_an_explicit_confirmation_phrase(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('app:seed-test-data', ['--force' => true, '--confirm' => 'incorrecta'])
            ->expectsOutput('Producción requiere --force --confirm=CARGAR-DATOS-DE-PRUEBA.')
            ->assertExitCode(1);
    }
}
