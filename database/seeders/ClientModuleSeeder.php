<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use App\Models\CollectionRoute;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientModuleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@financiera.test'], ['name' => 'Administrador', 'password' => 'password']);
        $product = CreditProduct::updateOrCreate(['code' => 'MICRO-01'], ['name' => 'Microcrédito comercial', 'currency' => 'NIO', 'allowed_frequencies' => ['weekly', 'biweekly', 'monthly'], 'allowed_interest_methods' => ['flat', 'declining_balance', 'french'], 'payment_allocation_order' => ['delinquency', 'fees', 'interest', 'principal'], 'minimum_term' => 4, 'maximum_term' => 60, 'is_active' => true]);
        $branch = Branch::firstOrCreate(['code' => 'EST-01'], ['name' => 'Oficina Central Estelí', 'address' => 'Estelí']);
        $zone = Zone::firstOrCreate(['code' => 'EST-CEN'], ['branch_id' => $branch->id, 'name' => 'Estelí Centro']);
        $sellers = collect(['Carlos Ruiz', 'Diana Mena', 'Ana López'])->map(function ($name, $index) use ($branch, $zone) {
            $user = User::updateOrCreate(['email' => 'vendedor'.($index + 1).'@financiera.test'], ['name' => $name, 'password' => 'password']);

            return SellerProfile::updateOrCreate(['user_id' => $user->id], ['branch_id' => $branch->id, 'zone_id' => $zone->id, 'code' => 'VEN-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'status' => 'active']);
        });
        $clients = collect();
        foreach ([
            ['CLI-000001', 'María López Herrera', '1990-05-12', '0001', '8888-1201', 'El Calvario', 13.0941, -86.3570, 18500, 9200],
            ['CLI-000002', 'José Antonio Pérez', '1984-08-18', '0002', '8777-2302', 'Villa Esperanza', 13.0875, -86.3493, 24000, 13800],
            ['CLI-000003', 'Rosa Emilia Gómez', '1992-03-22', '0003', '8666-3403', 'Oscar Gámez', 13.0982, -86.3426, 16000, 8500],
        ] as $index => [$code, $name, $birthDate, $serial, $phone, $neighborhood, $latitude, $longitude, $income, $expenses]) {
            $client = Client::updateOrCreate(['code' => $code], ['full_name' => $name, 'identity_number' => $this->identity('441', $birthDate, $serial), 'birth_date' => $birthDate, 'phone' => $phone, 'address' => 'De la iglesia del barrio, dos cuadras al norte', 'department' => 'Estelí', 'municipality' => 'Estelí', 'neighborhood' => $neighborhood, 'latitude' => $latitude, 'longitude' => $longitude, 'economic_activity' => 'Comercio', 'estimated_income' => $income, 'estimated_expenses' => $expenses, 'status' => 'active']);
            $clients->push($client);
            ClientPortfolioAssignment::firstOrCreate(['client_id' => $client->id, 'active_guard' => 'ACTIVE'], ['seller_id' => $sellers[$index]->id, 'assigned_at' => now(), 'reason' => 'Asignación inicial', 'assigned_by' => $admin->id]);
            $application = CreditApplication::updateOrCreate(['number' => 'SOL-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT)], ['client_id' => $client->id, 'seller_id' => $sellers[$index]->id, 'credit_product_id' => $product->id, 'status' => 'disbursed', 'requested_amount' => 20000 + ($index * 5000), 'approved_amount' => 20000 + ($index * 5000), 'currency' => 'NIO', 'purpose' => 'Capital de trabajo', 'term' => 20, 'payment_frequency' => 'weekly', 'economic_snapshot' => [], 'decided_by' => $admin->id, 'decided_at' => now()]);
            Loan::updateOrCreate(['number' => 'PRE-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT)], ['credit_application_id' => $application->id, 'client_id' => $client->id, 'seller_id' => $sellers[$index]->id, 'status' => 'active', 'currency' => 'NIO', 'principal' => 20000 + ($index * 5000), 'principal_balance' => 15000 + ($index * 4000), 'interest_balance' => 1200 + ($index * 300), 'fee_balance' => 0, 'approved_terms' => ['term' => 20, 'frequency' => 'weekly'], 'disbursed_at' => today()->subMonths(2)]);
        }
        $route = CollectionRoute::updateOrCreate(['code' => 'RUT-000001'], ['name' => 'Estelí Centro', 'scheduled_date' => today(), 'collector_id' => $sellers[0]->id, 'starts_at' => '08:00', 'status' => 'active']);
        foreach ($clients as $position => $client) {
            $route->stops()->updateOrCreate(['client_id' => $client->id], ['position' => $position + 1, 'status' => $position === 0 ? 'visited' : 'pending', 'visited_at' => $position === 0 ? now() : null]);
        }
        DB::table('document_sequences')->updateOrInsert(['key' => 'client'], ['prefix' => 'CLI-', 'next_number' => 4, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('document_sequences')->updateOrInsert(['key' => 'credit_application'], ['prefix' => 'SOL-', 'next_number' => 4, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('document_sequences')->updateOrInsert(['key' => 'collection_route'], ['prefix' => 'RUT-', 'next_number' => 2, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function identity(string $municipality, string $birthDate, string $serial): string
    {
        $date = date('dmy', strtotime($birthDate));
        $number = $municipality.$date.$serial;
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXY';

        return $municipality.'-'.$date.'-'.$serial.$letters[(int) $number % 23];
    }
}
