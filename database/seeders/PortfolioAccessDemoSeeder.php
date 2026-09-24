<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\DocumentSequenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PortfolioAccessDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'admin@financiera.test')->firstOrFail();
        $sellers = SellerProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->whereIn('name', ['Ana López', 'Carlos Ruiz']))
            ->get()
            ->keyBy(fn (SellerProfile $seller) => $seller->user->name);

        $rows = [
            ['seller' => 'Ana López', 'full_name' => 'Lucía Martínez Gómez', 'phone' => '8721-4101', 'address' => 'Barrio El Calvario, de la iglesia 2 cuadras al norte', 'neighborhood' => 'El Calvario', 'job_position' => 'Comerciante', 'workplace' => 'Pulpería Lucía', 'estimated_income' => '18500.00', 'other_income' => '1200.00', 'estimated_expenses' => '8200.00'],
            ['seller' => 'Ana López', 'full_name' => 'Pedro José Hernández', 'phone' => '8721-4102', 'address' => 'Barrio Rosario, casa 18', 'neighborhood' => 'Rosario', 'job_position' => 'Carpintero', 'workplace' => 'Taller San José', 'estimated_income' => '16200.00', 'other_income' => '800.00', 'estimated_expenses' => '7100.00'],
            ['seller' => 'Ana López', 'full_name' => 'Sofía Ramírez Castillo', 'phone' => '8721-4103', 'address' => 'Villa Cuba, sector norte', 'neighborhood' => 'Villa Cuba', 'job_position' => 'Costurera', 'workplace' => 'Confecciones Sofía', 'estimated_income' => '14800.00', 'other_income' => '1500.00', 'estimated_expenses' => '6500.00'],
            ['seller' => 'Carlos Ruiz', 'full_name' => 'Miguel Ángel Torres', 'phone' => '8832-5201', 'address' => 'Barrio Oscar Gámez, calle principal', 'neighborhood' => 'Oscar Gámez', 'job_position' => 'Mecánico', 'workplace' => 'Taller Torres', 'estimated_income' => '20500.00', 'other_income' => '1000.00', 'estimated_expenses' => '9300.00'],
            ['seller' => 'Carlos Ruiz', 'full_name' => 'Valeria Mendoza Ruiz', 'phone' => '8832-5202', 'address' => 'Barrio 14 de Abril, casa 24', 'neighborhood' => '14 de Abril', 'job_position' => 'Vendedora', 'workplace' => 'Mercado Alfredo Lazo', 'estimated_income' => '17100.00', 'other_income' => '900.00', 'estimated_expenses' => '7600.00'],
            ['seller' => 'Carlos Ruiz', 'full_name' => 'José Manuel Flores', 'phone' => '8832-5203', 'address' => 'Barrio La Comuna, entrada sur', 'neighborhood' => 'La Comuna', 'job_position' => 'Panadero', 'workplace' => 'Panadería El Trigal', 'estimated_income' => '19300.00', 'other_income' => '700.00', 'estimated_expenses' => '8400.00'],
        ];

        DB::transaction(function () use ($actor, $sellers, $rows): void {
            foreach ($rows as $row) {
                $seller = $sellers->get($row['seller']);
                if (! $seller) {
                    $this->command?->warn("No se encontró el perfil activo de {$row['seller']}.");

                    continue;
                }

                $client = Client::query()->where('phone', $row['phone'])->lockForUpdate()->first();
                if (! $client) {
                    $client = new Client(['code' => app(DocumentSequenceService::class)->next('client', 'CLI-')]);
                }
                $client->fill(collect($row)->except('seller')->all() + [
                    'department' => 'Estelí',
                    'municipality' => 'Estelí',
                    'status' => 'active',
                ])->save();

                $activeAssignment = $client->activeAssignment()->lockForUpdate()->first();
                if ($activeAssignment?->seller_id === $seller->id) {
                    continue;
                }
                if ($activeAssignment) {
                    $activeAssignment->update(['ended_at' => now(), 'active_guard' => null]);
                }
                ClientPortfolioAssignment::query()->create([
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                    'previous_seller_id' => $activeAssignment?->seller_id,
                    'assigned_at' => now(),
                    'active_guard' => 'ACTIVE',
                    'reason' => 'Datos de demostración para aislamiento de cartera',
                    'assigned_by' => $actor->id,
                ]);
            }
        });
    }
}
