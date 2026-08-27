<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CollectionActivityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@financiera.test')->first() ?? User::query()->firstOrFail();
        $clients = Client::query()->orderBy('id')->get();
        $loans = Loan::query()->get()->keyBy('client_id');
        $collectors = SellerProfile::query()->with('user')->orderBy('id')->get();

        if ($clients->isEmpty() || $collectors->isEmpty()) {
            return;
        }

        $routes = collect([
            $this->route('RUT-000001', 'Estelí Centro', today(), $collectors[0], '08:00'),
            $this->route('RUT-DEMO-02', 'Estelí Norte', today()->subDay(), $collectors[1] ?? $collectors[0], '09:00'),
            $this->route('RUT-DEMO-03', 'Oscar Gámez', today()->subDays(3), $collectors[2] ?? $collectors[0], '07:30'),
            $this->route('RUT-DEMO-04', 'Estelí Sur', today()->addDay(), $collectors[1] ?? $collectors[0], '08:30', 'planned'),
            $this->route('RUT-DEMO-05', 'La Trinidad', today()->addDays(3), $collectors[2] ?? $collectors[0], '09:15', 'planned'),
        ]);

        $stops = $routes->flatMap(function (CollectionRoute $route) use ($clients) {
            return $clients->values()->map(function (Client $client, int $index) use ($route) {
                return $route->stops()->updateOrCreate(
                    ['client_id' => $client->id],
                    [
                        'position' => $index + 1,
                        'status' => $route->scheduled_date->isToday()
                            ? ($index === 0 ? 'visited' : 'pending')
                            : ($route->scheduled_date->isFuture() ? 'pending' : 'visited'),
                        'visited_at' => ($route->scheduled_date->isToday() && $index !== 0) || $route->scheduled_date->isFuture()
                            ? null
                            : now(),
                    ]
                );
            });
        })->values();

        $samples = [
            [$stops[0], $collectors[0], 'collected', '850.00', 'cash', null, 'Pago recibido en domicilio', now()->subHours(2)],
            [$stops[1], $collectors[0], 'promise', null, null, today()->addDays(3), 'Promete pagar el viernes en la oficina', now()->subHours(1)],
            [$stops[2], $collectors[0], 'no_payment', null, null, null, 'Estaba en el mercado, sin efectivo', now()->subMinutes(40)],
            [$stops[3] ?? $stops[0], $collectors[1] ?? $collectors[0], 'collected', '1200.00', 'transfer', null, 'Transferencia BAC · ref 458912', now()->subDay()->setTime(10, 15)],
            [$stops[4] ?? $stops[1], $collectors[1] ?? $collectors[0], 'not_found', null, null, null, 'Nadie atendió en la vivienda', now()->subDay()->setTime(11, 5)],
            [$stops[5] ?? $stops[2], $collectors[1] ?? $collectors[0], 'collected', '430.00', 'deposit', null, 'Depósito en Banpro', now()->subDay()->setTime(16, 40)],
            [$stops[6] ?? $stops[0], $collectors[2] ?? $collectors[0], 'promise', null, null, today()->addDays(5), 'Espera el cobro del negocio', now()->subDays(3)->setTime(8, 20)],
            [$stops[7] ?? $stops[1], $collectors[2] ?? $collectors[0], 'collected', '2100.00', 'cash', null, 'Abono a dos cuotas', now()->subDays(3)->setTime(9, 10)],
            [$stops[8] ?? $stops[2], $collectors[2] ?? $collectors[0], 'no_payment', null, null, null, 'Pidió que regresaran la próxima semana', now()->subDays(3)->setTime(12, 45)],
            [$stops[0], $collectors[0], 'collected', '600.00', 'cash', null, 'Abono parcial de la cuota 2', now()->subDays(6)->setTime(14, 30)],
        ];

        foreach ($samples as [$stop, $collector, $outcome, $amount, $method, $promiseDate, $notes, $recordedAt]) {
            CollectionRecord::query()->updateOrCreate(
                [
                    'collection_route_stop_id' => $stop->id,
                    'outcome' => $outcome,
                    'recorded_at' => $recordedAt,
                ],
                [
                    'idempotency_key' => (string) Str::uuid(),
                    'client_id' => $stop->client_id,
                    'loan_id' => $loans[$stop->client_id]->id ?? null,
                    'collector_id' => $collector->id,
                    'amount' => $amount,
                    'currency' => 'NIO',
                    'payment_method' => $method,
                    'promise_date' => $promiseDate,
                    'notes' => $notes,
                    'application_status' => $outcome === 'collected' ? 'pending' : 'not_applicable',
                    'recorded_by' => $admin->id,
                ]
            );
        }
    }

    private function route(string $code, string $name, $date, SellerProfile $collector, string $startsAt, ?string $status = null): CollectionRoute
    {
        return CollectionRoute::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'scheduled_date' => $date,
                'collector_id' => $collector->id,
                'starts_at' => $startsAt,
                'status' => $status ?? ($date->isToday() ? 'active' : ($date->isFuture() ? 'planned' : 'completed')),
            ]
        );
    }
}
