<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use App\Models\CollectionRoute;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('is_active', true)->oldest()->first();
        if (! $actor) {
            throw new RuntimeException('Debe existir al menos un usuario activo antes de cargar datos de prueba.');
        }

        DB::transaction(function () use ($actor): void {
            $branch = Branch::query()->updateOrCreate(
                ['code' => 'DEMO-EST-01'],
                ['name' => 'Sucursal Estelí Centro', 'address' => 'Estelí, Nicaragua', 'is_active' => true]
            );

            $zones = collect([
                ['DEMO-EST-CEN', 'Estelí Centro'],
                ['DEMO-EST-NOR', 'Estelí Norte'],
                ['DEMO-EST-SUR', 'Estelí Sur'],
            ])->map(fn (array $zone) => Zone::query()->updateOrCreate(
                ['code' => $zone[0]],
                ['branch_id' => $branch->id, 'name' => $zone[1], 'is_active' => true]
            ));

            $collaborators = collect([
                ['DEMO-GES-001', 'María Fernanda López', 'maria.lopez@example.invalid', 0],
                ['DEMO-GES-002', 'Carlos Antonio Ruiz', 'carlos.ruiz@example.invalid', 1],
                ['DEMO-GES-003', 'Diana Patricia Mena', 'diana.mena@example.invalid', 2],
            ])->map(fn (array $row) => SellerProfile::query()->updateOrCreate(
                ['code' => $row[0]],
                [
                    'user_id' => null,
                    'branch_id' => $branch->id,
                    'zone_id' => $zones[$row[3]]->id,
                    'full_name' => $row[1],
                    'email' => $row[2],
                    'hired_at' => today()->subMonths(6),
                    'capabilities' => array_keys(SellerProfile::CAPABILITIES),
                    'notes' => 'Registro demostrativo para validación funcional.',
                    'status' => 'active',
                ]
            ));

            $product = CreditProduct::query()->updateOrCreate(
                ['code' => 'DEMO-CREDITO'],
                [
                    'name' => 'Crédito de prueba sin política financiera',
                    'currency' => 'NIO',
                    'allowed_frequencies' => ['weekly', 'biweekly', 'monthly'],
                    'allowed_interest_methods' => ['flat', 'declining_balance', 'french'],
                    'default_interest_rate' => null,
                    'default_interest_method' => null,
                    'default_administrative_fee' => '0.00',
                    'delinquency_method' => null,
                    'delinquency_rate' => null,
                    'payment_allocation_order' => null,
                    'minimum_term' => 4,
                    'maximum_term' => 52,
                    'is_active' => true,
                ]
            );

            $people = [
                ['Ana Lucía Martínez', 'El Calvario', 13.0941, -86.3570, 18500, 9200],
                ['José Ramón Pérez', 'Villa Esperanza', 13.0875, -86.3493, 24000, 13800],
                ['Rosa Emilia Gómez', 'Oscar Gámez', 13.0982, -86.3426, 16000, 8500],
                ['Luis Alberto Mendoza', 'La Unión', 13.0860, -86.3610, 21000, 11000],
                ['Carmen Soto Blandón', 'San Antonio', 13.1025, -86.3480, 14500, 7800],
                ['Pedro Ramírez Cruz', 'El Rosario', 13.0795, -86.3555, 26800, 14200],
                ['Isabel Cruz Herrera', 'Centro', 13.0935, -86.3538, 17200, 9100],
                ['Roberto Calero Díaz', 'Villa Libertad', 13.0910, -86.3380, 19800, 10200],
            ];

            $clients = collect($people)->map(function (array $person, int $index) use ($collaborators, $actor) {
                [$name, $neighborhood, $latitude, $longitude, $income, $expenses] = $person;
                $number = $index + 1;
                $client = Client::withTrashed()->updateOrCreate(
                    ['code' => 'DEMO-CLI-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)],
                    [
                        'full_name' => $name,
                        'identity_number' => null,
                        'birth_date' => now()->subYears(25 + $index)->subMonths($index)->toDateString(),
                        'phone' => '8000-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                        'email' => 'cliente'.$number.'@example.invalid',
                        'address' => 'Dirección demostrativa, '.$neighborhood.', Estelí',
                        'department' => 'Estelí',
                        'municipality' => 'Estelí',
                        'neighborhood' => $neighborhood,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'economic_activity' => $index % 2 === 0 ? 'Comercio minorista' : 'Servicios',
                        'estimated_income' => $income,
                        'estimated_expenses' => $expenses,
                        'status' => 'active',
                        'notes' => 'Cliente ficticio creado para pruebas; no corresponde a una persona real.',
                        'deleted_at' => null,
                    ]
                );
                if ($client->trashed()) {
                    $client->restore();
                }
                $seller = $collaborators[$index % $collaborators->count()];
                ClientPortfolioAssignment::query()->firstOrCreate(
                    ['client_id' => $client->id, 'active_guard' => 'ACTIVE'],
                    [
                        'seller_id' => $seller->id,
                        'assigned_at' => now()->subWeeks(4),
                        'reason' => 'Asignación demostrativa para validación funcional.',
                        'assigned_by' => $actor->id,
                    ]
                );

                return $client;
            });

            $applications = collect([
                [0, 'submitted', '15000.00', null, 9],
                [1, 'review', '22000.00', null, 5],
                [2, 'approved', '18000.00', '18000.00', 3],
                [3, 'disbursed', '12000.00', '12000.00', 35],
                [4, 'disbursed', '16000.00', '16000.00', 12],
            ])->map(function (array $row, int $index) use ($clients, $collaborators, $product, $actor) {
                [$clientIndex, $status, $requested, $approved, $daysAgo] = $row;

                return CreditApplication::query()->updateOrCreate(
                    ['number' => 'DEMO-SOL-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                    [
                        'client_id' => $clients[$clientIndex]->id,
                        'seller_id' => $collaborators[$clientIndex % $collaborators->count()]->id,
                        'credit_product_id' => $product->id,
                        'status' => $status,
                        'requested_amount' => $requested,
                        'approved_amount' => $approved,
                        'currency' => 'NIO',
                        'purpose' => 'Escenario ficticio para validar el flujo del sistema.',
                        'applied_on' => today()->subDays($daysAgo),
                        'term' => 12,
                        'term_value' => 12,
                        'term_unit' => 'weeks',
                        'payment_frequency' => 'weekly',
                        'interest_rate' => null,
                        'interest_method' => null,
                        'administrative_fee' => '0.00',
                        'economic_snapshot' => ['source' => 'production_test_data', 'test_only' => true],
                        'seller_notes' => 'Datos ficticios para pruebas. La política financiera no está configurada.',
                        'decided_by' => in_array($status, ['approved', 'disbursed'], true) ? $actor->id : null,
                        'decided_at' => in_array($status, ['approved', 'disbursed'], true) ? now()->subDays(2) : null,
                        'approved_at' => in_array($status, ['approved', 'disbursed'], true) ? now()->subDays(2) : null,
                    ]
                );
            });

            $this->loan($applications[3], $clients[3], $collaborators[0], $actor, 'DEMO-PRE-001', '12000.00', today()->subDays(32), [
                [1, today()->subDays(18), '1000.00'], [2, today()->subDays(7), '1000.00'], [3, today(), '1000.00'], [4, today()->addDays(7), '1000.00'],
            ]);
            $this->loan($applications[4], $clients[4], $collaborators[1], $actor, 'DEMO-PRE-002', '16000.00', today()->subDays(10), [
                [1, today(), '2000.00'], [2, today()->addDays(14), '2000.00'], [3, today()->addDays(28), '2000.00'],
            ]);

            $route = CollectionRoute::query()->updateOrCreate(
                ['code' => 'DEMO-RUT-001'],
                ['name' => 'Ruta demostrativa Estelí Centro', 'scheduled_date' => today(), 'collector_id' => $collaborators[0]->id, 'status' => 'active', 'starts_at' => '08:00', 'notes' => 'Ruta ficticia para pruebas.']
            );
            foreach ($clients->take(6) as $position => $client) {
                $route->stops()->updateOrCreate(
                    ['client_id' => $client->id],
                    ['position' => $position + 1, 'status' => 'pending', 'visited_at' => null, 'notes' => 'Parada demostrativa.']
                );
            }
        });
    }

    private function loan(CreditApplication $application, Client $client, SellerProfile $seller, User $actor, string $number, string $principal, $disbursedAt, array $installments): void
    {
        $loan = Loan::query()->updateOrCreate(
            ['number' => $number],
            [
                'credit_application_id' => $application->id,
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'status' => 'active',
                'currency' => 'NIO',
                'principal' => $principal,
                'principal_balance' => $principal,
                'interest_balance' => '0.00',
                'fee_balance' => '0.00',
                'delinquency_balance' => '0.00',
                'delinquency_method' => null,
                'delinquency_daily_rate' => null,
                'delinquency_fixed_amount' => null,
                'approved_terms' => ['test_only' => true, 'interest_policy' => 'not_configured'],
                'disbursed_at' => $disbursedAt,
            ]
        );

        LoanDisbursement::query()->firstOrCreate(
            ['number' => str_replace('PRE', 'DES', $number)],
            [
                'idempotency_key' => (string) Str::uuid(),
                'credit_application_id' => $application->id,
                'loan_id' => $loan->id,
                'amount' => $principal,
                'currency' => 'NIO',
                'payment_method' => 'test_record',
                'reference' => 'DATOS-DE-PRUEBA',
                'disbursed_at' => $disbursedAt,
                'disbursed_by' => $actor->id,
            ]
        );

        foreach ($installments as [$installmentNumber, $dueDate, $amount]) {
            LoanInstallment::query()->updateOrCreate(
                ['loan_id' => $loan->id, 'number' => $installmentNumber],
                [
                    'due_date' => $dueDate,
                    'principal_due' => $amount,
                    'interest_due' => '0.00',
                    'fees_due' => '0.00',
                    'delinquency_due' => '0.00',
                    'principal_paid' => '0.00',
                    'interest_paid' => '0.00',
                    'fees_paid' => '0.00',
                    'delinquency_paid' => '0.00',
                    'paid_amount' => '0.00',
                    'status' => 'pending',
                ]
            );
        }
    }
}
