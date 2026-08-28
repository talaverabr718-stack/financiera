<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientPortfolioAssignment;
use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use App\Services\DelinquencyTrackingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OperationsBoardSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ClientModuleSeeder::class);

        $admin = User::query()->where('email', 'admin@financiera.test')->firstOrFail();
        $product = CreditProduct::query()->where('code', 'MICRO-01')->firstOrFail();
        $sellers = SellerProfile::query()->with('user')->orderBy('code')->get();
        $norte = Zone::query()->firstOrCreate(
            ['code' => 'EST-NOR'],
            ['branch_id' => $sellers[0]->branch_id, 'name' => 'Estelí Norte']
        );
        $sur = Zone::query()->firstOrCreate(
            ['code' => 'EST-SUR'],
            ['branch_id' => $sellers[0]->branch_id, 'name' => 'Estelí Sur']
        );
        $sellers[1]->update(['zone_id' => $norte->id]);
        $sellers[2]->update(['zone_id' => $sur->id]);

        $people = [
            ['CLI-000004', 'Luis Alberto Mendoza', '1986-11-03', '0004', '8855-4404', 'La Unión', 13.0860, -86.3610, 21000, 11000, $sellers[1]],
            ['CLI-000005', 'Carmen Soto Blandón', '1995-02-14', '0005', '8788-5505', 'San Antonio', 13.1025, -86.3480, 14500, 7800, $sellers[0]],
            ['CLI-000006', 'Pedro Ramírez Cruz', '1979-07-09', '0006', '8677-6606', 'El Rosario', 13.0795, -86.3555, 26800, 14200, $sellers[1]],
            ['CLI-000007', 'Ana Isabel Cruz', '1991-12-28', '0007', '8566-7707', 'Centro', 13.0935, -86.3538, 17200, 9100, $sellers[2]],
            ['CLI-000008', 'Roberto Calero', '1988-04-16', '0008', '8455-8808', 'Villa Libertad', 13.0910, -86.3380, 19800, 10200, $sellers[0]],
            ['CLI-000009', 'Elena Vásquez', '1993-09-21', '0009', '8344-9909', 'La Trinidad', 13.1080, -86.3500, 15600, 8300, $sellers[1]],
            ['CLI-000010', 'Miguel Ángel Toruño', '1982-01-30', '0010', '8233-1010', 'Oscar Gámez', 13.0994, -86.3398, 22400, 12100, $sellers[2]],
        ];

        $extra = collect();
        foreach ($people as $index => [$code, $name, $birthDate, $serial, $phone, $neighborhood, $lat, $lng, $income, $expenses, $seller]) {
            $client = Client::query()->updateOrCreate(
                ['code' => $code],
                [
                    'full_name' => $name,
                    'identity_number' => $this->identity('441', $birthDate, $serial),
                    'birth_date' => $birthDate,
                    'phone' => $phone,
                    'address' => 'De la esquina de la pulpería, una cuadra al este',
                    'department' => 'Estelí',
                    'municipality' => 'Estelí',
                    'neighborhood' => $neighborhood,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'economic_activity' => $index % 2 ? 'Venta de ropa' : 'Pulpería',
                    'estimated_income' => $income,
                    'estimated_expenses' => $expenses,
                    'status' => 'active',
                ]
            );
            ClientPortfolioAssignment::query()->firstOrCreate(
                ['client_id' => $client->id, 'active_guard' => 'ACTIVE'],
                ['seller_id' => $seller->id, 'assigned_at' => now()->subWeeks(3), 'reason' => 'Cartera de demostración', 'assigned_by' => $admin->id]
            );
            $extra[$code] = $client;
        }

        $this->queueApplication('SOL-000004', $extra['CLI-000005'], $sellers[0], $product, $admin, 'submitted', 15000, 6, null);
        $this->queueApplication('SOL-000005', $extra['CLI-000006'], $sellers[1], $product, $admin, 'review', 22000, 2, null);
        $this->queueApplication('SOL-000006', $extra['CLI-000007'], $sellers[2], $product, $admin, 'approved', 12000, 9, 12000);

        $luis = $this->loanFor($extra['CLI-000004'], $sellers[1], $product, $admin, 'SOL-000007', 'PRE-000004', 18000, today()->subDays(4), 16500);
        $elena = $this->loanFor($extra['CLI-000009'], $sellers[1], $product, $admin, 'SOL-000008', 'PRE-000005', 16000, now()->subMonthNoOverflow()->startOfMonth()->addDays(6), 14800);
        $roberto = $this->loanFor($extra['CLI-000008'], $sellers[0], $product, $admin, 'SOL-000009', 'PRE-000006', 9000, today()->subWeeks(6), 7200);
        $miguel = $this->loanFor($extra['CLI-000010'], $sellers[2], $product, $admin, 'SOL-000010', 'PRE-000007', 14000, today()->subWeeks(8), 11800);

        $maria = Loan::query()->where('number', 'PRE-000001')->firstOrFail();
        $jose = Loan::query()->where('number', 'PRE-000002')->firstOrFail();
        $rosa = Loan::query()->where('number', 'PRE-000003')->firstOrFail();

        $this->schedule($maria, [
            [1, today()->subDays(21), '1000.00', '1000.00'],
            [2, today(), '850.00', '0.00'],
            [3, today()->addDays(7), '850.00', '0.00'],
        ], '15000.00');
        $this->schedule($jose, [
            [1, today()->subDays(12), '1400.00', '0.00'],
            [2, today(), '1100.00', '0.00'],
            [3, today()->addDays(14), '1100.00', '0.00'],
        ], '19000.00');
        $this->schedule($rosa, [
            [1, today()->subDays(45), '2800.00', '0.00'],
            [2, today()->subDays(14), '900.00', '0.00'],
            [3, today()->addDays(7), '900.00', '0.00'],
        ], '23000.00');
        $this->schedule($luis, [
            [1, today()->addDays(5), '1500.00', '650.00'],
        ], '16500.00');
        $this->schedule($elena, [
            [1, today()->addDays(3), '2000.00', '0.00'],
        ], '14800.00');
        $this->schedule($roberto, [
            [1, today()->subDays(5), '720.00', '0.00'],
            [2, today()->addDays(9), '720.00', '0.00'],
        ], '7200.00');
        $this->schedule($miguel, [
            [1, today()->subDays(18), '1650.00', '0.00'],
        ], '11800.00');

        $this->recordPayment($maria, 1, '1000.00', $admin, today()->subDays(14)->setTime(10, 15), 'Pago de la cuota 1 en oficina');

        $centro = $this->route('RUT-000001', 'Estelí Centro', today(), $sellers[0], '08:00', 'active');
        $surRoute = $this->route('RUT-MESA-02', 'Estelí Sur', today(), $sellers[1], '09:15', 'active');
        $ayer = $this->route('RUT-MESA-03', 'Estelí Norte', today()->subDay(), $sellers[1], '08:30', 'completed');

        $mariaClient = Client::query()->where('code', 'CLI-000001')->firstOrFail();
        $joseClient = Client::query()->where('code', 'CLI-000002')->firstOrFail();
        $rosaClient = Client::query()->where('code', 'CLI-000003')->firstOrFail();

        $stopMaria = $this->stop($centro, $mariaClient, 1, 'visited', now()->setTime(8, 40));
        $stopJose = $this->stop($centro, $joseClient, 2, 'pending');
        $stopRosa = $this->stop($centro, $rosaClient, 3, 'pending');
        $stopRoberto = $this->stop($centro, $extra['CLI-000008'], 4, 'pending');
        $stopLuis = $this->stop($surRoute, $extra['CLI-000004'], 1, 'visited', now()->setTime(9, 35));
        $stopElena = $this->stop($surRoute, $extra['CLI-000009'], 2, 'pending');
        $stopMiguel = $this->stop($surRoute, $extra['CLI-000010'], 3, 'not_found', now()->setTime(10, 5));
        $stopAyer1 = $this->stop($ayer, $joseClient, 1, 'visited', now()->subDay()->setTime(10, 20));
        $stopAyer2 = $this->stop($ayer, $extra['CLI-000009'], 2, 'visited', now()->subDay()->setTime(11, 10));

        $this->activity('mesa-today-maria', $stopMaria, $sellers[0], $maria, $admin, 'collected', '800.00', 'cash', null, 'Cuota del día cobrada en El Calvario', today()->setTime(8, 42));
        $this->activity('mesa-today-jose', $stopJose, $sellers[0], $jose, $admin, 'promise', null, null, today(), 'Promete pagar hoy en la oficina, después del mercado', today()->setTime(9, 10));
        $this->activity('mesa-today-rosa', $stopRosa, $sellers[0], $rosa, $admin, 'no_payment', null, null, null, 'Estaba en el mercado, sin efectivo', today()->setTime(9, 28));
        $this->activity('mesa-today-luis', $stopLuis, $sellers[1], $luis, $admin, 'collected', '650.00', 'transfer', null, 'Transferencia BAC · ref 448211', today()->setTime(9, 38));
        $this->activity('mesa-today-miguel', $stopMiguel, $sellers[1], $miguel, $admin, 'not_found', null, null, null, 'Nadie atendió en la vivienda', today()->setTime(10, 8));
        $this->activity('mesa-yday-jose', $stopAyer1, $sellers[1], $jose, $admin, 'collected', '1200.00', 'cash', null, 'Abono a mora en Villa Esperanza', today()->subDay()->setTime(10, 22));
        $this->activity('mesa-yday-elena', $stopAyer2, $sellers[1], $elena, $admin, 'collected', '430.00', 'deposit', null, 'Depósito Banpro La Trinidad', today()->subDay()->setTime(16, 40));
        $this->activity('mesa-d3-rosa', $stopRosa, $sellers[2], $rosa, $admin, 'collected', '2100.00', 'cash', null, 'Abono fuerte a cuota vencida', today()->subDays(3)->setTime(9, 10));
        $this->activity('mesa-d3-roberto', $stopRoberto, $sellers[0], $roberto, $admin, 'promise', null, null, today(), 'Espera el cobro del negocio y paga hoy', today()->subDays(3)->setTime(12, 45));
        $this->activity('mesa-d4-miguel', $stopMiguel, $sellers[2], $miguel, $admin, 'collected', '1400.00', 'cash', null, 'Pago parcial de la cuota 1', today()->subDays(4)->setTime(11, 20));
        $this->activity('mesa-d6-maria', $stopMaria, $sellers[0], $maria, $admin, 'collected', '600.00', 'cash', null, 'Abono de la semana pasada', today()->subDays(6)->setTime(14, 30));

        $this->recordPayment($luis, 1, '650.00', $admin, today()->setTime(9, 38), 'Abono anticipado de demostración', 'REC-MESA-LUIS-1');

        app(DelinquencyTrackingService::class)->recalculateDueLoans(now(), ['trigger' => 'demo', 'actor_id' => $admin->id]);

        DB::table('document_sequences')->updateOrInsert(['key' => 'client'], ['prefix' => 'CLI-', 'next_number' => 11, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('document_sequences')->updateOrInsert(['key' => 'credit_application'], ['prefix' => 'SOL-', 'next_number' => 11, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('document_sequences')->updateOrInsert(['key' => 'loan'], ['prefix' => 'PRE-', 'next_number' => 8, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('document_sequences')->updateOrInsert(['key' => 'collection_route'], ['prefix' => 'RUT-', 'next_number' => 10, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('document_sequences')->updateOrInsert(['key' => 'payment'], ['prefix' => 'REC-', 'next_number' => 20, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function queueApplication(string $number, Client $client, SellerProfile $seller, CreditProduct $product, User $admin, string $status, string $amount, int $daysWaiting, ?int $approved): void
    {
        $application = CreditApplication::query()->updateOrCreate(
            ['number' => $number],
            [
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'credit_product_id' => $product->id,
                'status' => $status,
                'requested_amount' => $amount,
                'approved_amount' => $approved,
                'currency' => 'NIO',
                'purpose' => 'Capital de trabajo para el negocio familiar',
                'term' => 16,
                'payment_frequency' => 'weekly',
                'economic_snapshot' => [],
                'applied_on' => today()->subDays($daysWaiting),
                'decided_by' => $status === 'approved' ? $admin->id : null,
                'decided_at' => $status === 'approved' ? now()->subDays(1) : null,
                'approved_at' => $status === 'approved' ? now()->subDays(1) : null,
            ]
        );
        $application->timestamps = false;
        $application->created_at = now()->subDays($daysWaiting)->setTime(9, 15);
        $application->updated_at = now()->subDays(min(1, $daysWaiting));
        $application->save();
    }

    private function loanFor(Client $client, SellerProfile $seller, CreditProduct $product, User $admin, string $applicationNumber, string $loanNumber, int $principal, $disbursedAt, int $principalBalance): Loan
    {
        $application = CreditApplication::query()->updateOrCreate(
            ['number' => $applicationNumber],
            [
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'credit_product_id' => $product->id,
                'status' => 'disbursed',
                'requested_amount' => $principal,
                'approved_amount' => $principal,
                'currency' => 'NIO',
                'purpose' => 'Capital de trabajo',
                'term' => 16,
                'payment_frequency' => 'weekly',
                'economic_snapshot' => [],
                'applied_on' => $disbursedAt,
                'decided_by' => $admin->id,
                'decided_at' => $disbursedAt,
                'approved_at' => $disbursedAt,
            ]
        );

        return Loan::query()->updateOrCreate(
            ['number' => $loanNumber],
            [
                'credit_application_id' => $application->id,
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'status' => 'active',
                'currency' => 'NIO',
                'principal' => $principal,
                'principal_balance' => $principalBalance,
                'interest_balance' => 0,
                'fee_balance' => 0,
                'approved_terms' => ['term' => 16, 'frequency' => 'weekly'],
                'disbursed_at' => $disbursedAt,
            ]
        );
    }

    /**
     * @param  list<array{0:int,1:\DateTimeInterface,2:string,3:string}>  $rows
     */
    private function schedule(Loan $loan, array $rows, string $principalBalance): void
    {
        foreach ($rows as [$number, $due, $amount, $paid]) {
            LoanInstallment::query()->updateOrCreate(
                ['loan_id' => $loan->id, 'number' => $number],
                [
                    'due_date' => $due,
                    'principal_due' => $amount,
                    'interest_due' => '0.00',
                    'fees_due' => '0.00',
                    'delinquency_due' => '0.00',
                    'principal_paid' => $paid,
                    'interest_paid' => '0.00',
                    'fees_paid' => '0.00',
                    'delinquency_paid' => '0.00',
                    'paid_amount' => $paid,
                    'status' => bccomp($paid, $amount, 2) >= 0 ? 'paid' : 'pending',
                ]
            );
        }
        $loan->update(['principal_balance' => $principalBalance, 'interest_balance' => 0, 'fee_balance' => 0]);
    }

    private function recordPayment(Loan $loan, int $installmentNumber, string $amount, User $actor, $receivedAt, string $notes, ?string $receipt = null): void
    {
        $installment = $loan->installments()->where('number', $installmentNumber)->first();
        if (! $installment) {
            return;
        }

        $payment = Payment::query()->updateOrCreate(
            ['receipt_number' => $receipt ?? 'REC-MESA-'.$loan->number.'-'.$installmentNumber],
            [
                'idempotency_key' => $this->uuid('pay-'.$loan->number.'-'.$installmentNumber),
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'collector_id' => $actor->id,
                'received_at' => $receivedAt,
                'amount' => $amount,
                'currency' => $loan->currency,
                'payment_method' => 'cash',
                'previous_balance' => $loan->outstanding_balance,
                'new_balance' => bcsub((string) $loan->outstanding_balance, $amount, 2),
                'status' => 'applied',
                'notes' => $notes,
                'created_by' => $actor->id,
            ]
        );

        if ($payment->allocations()->exists()) {
            return;
        }

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'installment_id' => $installment->id,
            'component' => 'principal',
            'amount' => $amount,
            'application_order' => 1,
            'policy_snapshot' => ['source' => 'demo', 'note' => 'Abono de demostración; no aplica motor de asignación.'],
        ]);
    }

    private function route(string $code, string $name, $date, SellerProfile $collector, string $startsAt, string $status): CollectionRoute
    {
        return CollectionRoute::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'scheduled_date' => $date,
                'collector_id' => $collector->id,
                'starts_at' => $startsAt,
                'status' => $status,
            ]
        );
    }

    private function stop(CollectionRoute $route, Client $client, int $position, string $status, $visitedAt = null)
    {
        return $route->stops()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'position' => $position,
                'status' => $status,
                'visited_at' => $visitedAt,
            ]
        );
    }

    private function activity(
        string $key,
        $stop,
        SellerProfile $collector,
        Loan $loan,
        User $admin,
        string $outcome,
        ?string $amount,
        ?string $method,
        $promiseDate,
        string $notes,
        $recordedAt
    ): void {
        CollectionRecord::query()->updateOrCreate(
            ['idempotency_key' => $this->uuid($key)],
            [
                'collection_route_stop_id' => $stop->id,
                'client_id' => $stop->client_id,
                'loan_id' => $loan->id,
                'collector_id' => $collector->id,
                'outcome' => $outcome,
                'amount' => $amount,
                'currency' => 'NIO',
                'payment_method' => $method,
                'promise_date' => $promiseDate,
                'notes' => $notes,
                'application_status' => $outcome === 'collected' ? 'pending' : 'not_applicable',
                'recorded_at' => $recordedAt,
                'recorded_by' => $admin->id,
            ]
        );
    }

    private function identity(string $municipality, string $birthDate, string $serial): string
    {
        $date = date('dmy', strtotime($birthDate));
        $number = $municipality.$date.$serial;
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXY';

        return $municipality.'-'.$date.'-'.$serial.$letters[(int) $number % 23];
    }

    private function uuid(string $key): string
    {
        $hash = substr(hash('sha1', 'financiera-mesa-'.$key), 0, 32);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 16, 3).'-'.substr($hash, 19, 12);
    }
}
