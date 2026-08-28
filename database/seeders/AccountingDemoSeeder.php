<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Seeder;

class AccountingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'admin@financiera.test')->first() ?? User::query()->firstOrFail();
        $accounts = collect([
            ['1101', 'Caja general', 'asset_current'], ['1102', 'Bancos moneda nacional', 'asset_current'],
            ['1201', 'Cuentas por cobrar', 'asset_current'], ['2101', 'Cuentas por pagar', 'liability_current'],
            ['3101', 'Capital aportado', 'equity'], ['4101', 'Ingresos por servicios financieros', 'revenue'],
            ['5101', 'Gastos administrativos', 'expense'], ['5102', 'Servicios básicos', 'expense'],
            ['5103', 'Papelería y suministros', 'expense'], ['5104', 'Gastos bancarios', 'expense'],
        ])->mapWithKeys(function (array $row) {
            [$code, $name, $type] = $row;
            $account = Account::query()->updateOrCreate(['code' => $code], ['name' => $name, 'description' => 'Cuenta demostrativa configurable', 'type' => $type, 'nature' => Account::NATURE_BY_TYPE[$type], 'level' => 1, 'is_postable' => true, 'is_active' => true]);
            return [$code => $account];
        });

        $centers = collect([
            ['ADM', 'Administración', 'Operación administrativa y dirección'],
            ['CJA', 'Caja y tesorería', 'Efectivo, bancos y conciliaciones'],
            ['COL', 'Cobranza', 'Gestión de recuperación de cartera'],
            ['CRE', 'Crédito', 'Análisis y formalización crediticia'],
        ])->mapWithKeys(fn (array $row) => [$row[0] => CostCenter::query()->updateOrCreate(['code' => $row[0]], ['name' => $row[1], 'description' => $row[2], 'is_active' => true])]);

        foreach ([2, 1, 0] as $monthsAgo) {
            $month = today()->subMonthsNoOverflow($monthsAgo);
            $period = AccountingPeriod::query()->firstOrCreate(['starts_on' => $month->copy()->startOfMonth()], ['name' => $month->translatedFormat('F Y'), 'ends_on' => $month->copy()->endOfMonth(), 'status' => 'open']);
            if ($period->status !== 'open') continue;
            $prefix = 'DEM-'.$month->format('Ym').'-';
            $samples = [
                ['001', 2, 'Aporte operativo a bancos', 'internal_support', 'AP-'.$month->format('Ym'), null, null, '1102', '3101', '125000.00', 'CJA'],
                ['002', 5, 'Ingresos operativos del período', 'receipt', 'REC-'.$month->format('Ym').'-01', 'Cliente demostrativo', 'RUC-DEMO-01', '1101', '4101', (string) (38500 + ($monthsAgo * 4200)).'.00', 'CRE'],
                ['003', 9, 'Depósito de efectivo en cuenta bancaria', 'bank_document', 'DEP-'.$month->format('Ym').'-01', 'Banco demostrativo', null, '1102', '1101', '28500.00', 'CJA'],
                ['004', 13, 'Compra de papelería y suministros', 'invoice', 'FAC-'.$month->format('Ym').'-18', 'Proveedor demostrativo', 'RUC-DEMO-02', '5103', '2101', '6750.00', 'ADM'],
                ['005', 17, 'Pago de servicios básicos', 'receipt', 'SER-'.$month->format('Ym').'-04', 'Proveedor de servicios', 'RUC-DEMO-03', '5102', '1102', '4890.00', 'ADM'],
                ['006', 21, 'Gastos administrativos del período', 'internal_support', 'GAD-'.$month->format('Ym'), null, null, '5101', '1102', '12400.00', 'ADM'],
                ['007', 24, 'Comisión bancaria', 'bank_document', 'NDB-'.$month->format('Ym').'-02', 'Banco demostrativo', null, '5104', '1102', '985.00', 'CJA'],
            ];
            foreach ($samples as $sample) $this->entry($prefix.$sample[0], $period, $month->copy()->day(min($sample[1], $month->daysInMonth)), $sample, $accounts, $centers, $user, 'posted');
        }

        $current = AccountingPeriod::query()->whereDate('starts_on', today()->startOfMonth())->firstOrFail();
        if ($current->status !== 'open') return;
        $draft = ['008', min(today()->day, 26), 'Provisión pendiente de revisión', 'internal_support', 'PROV-'.today()->format('Ym'), null, null, '5101', '2101', '8300.00', 'ADM'];
        $this->entry('DEM-'.today()->format('Ym').'-008', $current, today(), $draft, $accounts, $centers, $user, 'draft');

        $originalData = ['009', min(today()->day, 27), 'Registro bancario por corregir', 'bank_document', 'AJU-'.today()->format('Ym'), 'Banco demostrativo', null, '5104', '1102', '1450.00', 'CJA'];
        $original = $this->entry('DEM-'.today()->format('Ym').'-009', $current, today(), $originalData, $accounts, $centers, $user, 'reversed');
        $reversalData = ['010', min(today()->day, 28), 'Reversión de registro bancario', 'internal_support', $original->number, 'Banco demostrativo', null, '1102', '5104', '1450.00', 'CJA'];
        $reversal = $this->entry('DEM-'.today()->format('Ym').'-010', $current, today(), $reversalData, $accounts, $centers, $user, 'posted', $original->id);
        $original->update(['reversed_at' => now()]);

        if (! $original->auditEvents()->where('action', 'accounting.demo.reversed')->exists()) {
            app(AuditService::class)->record($original, 'accounting.demo.reversed', $user->id, ['status' => 'posted'], ['status' => 'reversed', 'reversal_id' => $reversal->id], 'Datos demostrativos para validación funcional');
        }
    }

    private function entry(string $number, AccountingPeriod $period, $date, array $sample, $accounts, $centers, User $user, string $status, ?int $reversalOf = null): JournalEntry
    {
        [, , $concept, $documentType, $documentNumber, $counterparty, $ruc, $debitCode, $creditCode, $amount, $centerCode] = $sample;
        $entry = JournalEntry::query()->updateOrCreate(['number' => $number], ['date' => $date, 'accounting_period_id' => $period->id, 'concept' => $concept, 'reference' => 'Escenario demostrativo', 'document_type' => $documentType, 'document_number' => $documentNumber, 'counterparty_name' => $counterparty, 'counterparty_ruc' => $ruc, 'status' => $status, 'total_debit' => $amount, 'total_credit' => $amount, 'reversal_of_id' => $reversalOf, 'user_id' => $user->id, 'posted_by_id' => $status === 'draft' ? null : $user->id, 'posted_at' => $status === 'draft' ? null : now(), 'notes' => 'Registro generado exclusivamente para demostración funcional.']);
        $entry->lines()->updateOrCreate(['detail' => 'Debe · '.$number], ['account_id' => $accounts[$debitCode]->id, 'cost_center_id' => $centers[$centerCode]->id, 'debit' => $amount, 'credit' => '0.00']);
        $entry->lines()->updateOrCreate(['detail' => 'Haber · '.$number], ['account_id' => $accounts[$creditCode]->id, 'cost_center_id' => $centers[$centerCode]->id, 'debit' => '0.00', 'credit' => $amount]);
        return $entry;
    }
}
