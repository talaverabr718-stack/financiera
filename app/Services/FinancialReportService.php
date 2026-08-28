<?php

namespace App\Services;

use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CreditApplication;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinancialReportService
{
    public const TYPES = ['portfolio' => 'Cartera', 'collections' => 'Cobranza', 'applications' => 'Solicitudes', 'disbursements' => 'Desembolsos', 'routes' => 'Rutas', 'accounting' => 'Contabilidad'];

    public function query(string $type, string $from, string $to, Request $request): Builder
    {
        $query = match ($type) {
            'portfolio' => Loan::with(['client', 'seller.user', 'application.product'])->whereBetween('disbursed_at', [$from, $to]),
            'collections' => CollectionRecord::with(['client', 'loan', 'collector.user', 'stop.route'])->whereBetween('recorded_at', [$from.' 00:00:00', $to.' 23:59:59']),
            'applications' => CreditApplication::with(['client', 'seller.user', 'product'])->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']),
            'disbursements' => LoanDisbursement::with(['application.client', 'loan', 'disbursedBy'])->whereBetween('disbursed_at', [$from, $to]),
            'routes' => CollectionRoute::with(['collector.user'])->withCount('stops')->whereBetween('scheduled_date', [$from, $to]),
            'accounting' => JournalEntry::with('user')->whereBetween('date', [$from, $to]),
        };
        if ($request->filled('status')) {
            $statusColumn = $type === 'collections' ? 'outcome' : ($type === 'disbursements' ? 'payment_method' : 'status');
            $query->where($statusColumn, $request->status);
        }
        if ($request->filled('client') && in_array($type, ['portfolio', 'collections', 'applications'], true)) {
            $query->where('client_id', $request->integer('client'));
        }
        if ($request->filled('seller')) {
            $column = in_array($type, ['portfolio', 'applications'], true) ? 'seller_id' : ($type === 'routes' ? 'collector_id' : ($type === 'collections' ? 'collector_id' : null));
            if ($column) {
                $query->where($column, $request->integer('seller'));
            }
        }

        return $query;
    }

    public function summary(string $type, Builder $query): array
    {
        return match ($type) {
            'portfolio' => ['count' => (clone $query)->count(), 'primary' => (clone $query)->sum('principal'), 'secondary' => (clone $query)->selectRaw('COALESCE(SUM(principal_balance+interest_balance+fee_balance+delinquency_balance),0) total')->value('total'), 'primary_label' => 'Monto colocado', 'secondary_label' => 'Saldo pendiente'],
            'collections' => ['count' => (clone $query)->count(), 'primary' => (clone $query)->where('outcome', 'collected')->sum('amount'), 'secondary' => (clone $query)->where('outcome', 'promise')->count(), 'primary_label' => 'Monto cobrado', 'secondary_label' => 'Promesas'],
            'applications' => ['count' => (clone $query)->count(), 'primary' => (clone $query)->sum('requested_amount'), 'secondary' => (clone $query)->sum('approved_amount'), 'primary_label' => 'Monto solicitado', 'secondary_label' => 'Monto aprobado'],
            'disbursements' => ['count' => (clone $query)->count(), 'primary' => (clone $query)->sum('amount'), 'secondary' => (clone $query)->where('payment_method', 'cash')->sum('amount'), 'primary_label' => 'Total desembolsado', 'secondary_label' => 'Entregado en efectivo'],
            'routes' => ['count' => (clone $query)->count(), 'primary' => (clone $query)->withCount('stops')->get()->sum('stops_count'), 'secondary' => (clone $query)->where('status', 'completed')->count(), 'primary_label' => 'Clientes programados', 'secondary_label' => 'Rutas finalizadas'],
            'accounting' => ['count' => (clone $query)->count(), 'primary' => (clone $query)->whereIn('status', ['posted', 'reversed'])->sum('total_debit'), 'secondary' => (clone $query)->where('status', 'draft')->count(), 'primary_label' => 'Movimientos contabilizados', 'secondary_label' => 'Borradores'],
        };
    }

    public function analytics(string $type, Builder $query): array
    {
        $items = (clone $query)->get();
        $totalAmount = $items->sum(fn ($item) => $this->metricAmount($type, $item));
        $trend = $items
            ->groupBy(fn ($item) => $this->dateValue($type, $item)?->format('Y-m') ?? 'Sin fecha')
            ->sortKeys()
            ->map(fn (Collection $group, string $period) => [
                'period' => $period,
                'label' => $period === 'Sin fecha' ? $period : now()->createFromFormat('Y-m', $period)->translatedFormat('M y'),
                'count' => $group->count(),
                'amount' => round($group->sum(fn ($item) => $this->metricAmount($type, $item)), 2),
            ])->values();

        $distribution = $items
            ->groupBy(fn ($item) => $this->dimensionValue($type, $item))
            ->map(function (Collection $group, string $label) use ($type, $totalAmount) {
                $amount = $group->sum(fn ($item) => $this->metricAmount($type, $item));

                return [
                    'label' => $this->humanize($label),
                    'count' => $group->count(),
                    'amount' => round($amount, 2),
                    'percentage' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 1) : 0,
                ];
            })->sortByDesc('amount')->values()->take(6);

        $leaders = $items
            ->groupBy(fn ($item) => $this->ownerName($type, $item))
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'count' => $group->count(),
                'amount' => round($group->sum(fn ($item) => $this->metricAmount($type, $item)), 2),
            ])->sortByDesc('amount')->values()->take(5);

        return [
            'trend' => $trend,
            'distribution' => $distribution,
            'leaders' => $leaders,
            'total_amount' => round($totalAmount, 2),
            'average_amount' => $items->count() ? round($totalAmount / $items->count(), 2) : 0,
            'records' => $items->count(),
        ];
    }

    public function statusOptions(string $type, Builder $query): array
    {
        $field = $type === 'collections' ? 'outcome' : ($type === 'disbursements' ? 'payment_method' : 'status');

        return (clone $query)->reorder()->whereNotNull($field)->distinct()->orderBy($field)->pluck($field)
            ->map(fn ($value) => ['value' => $value, 'label' => $this->humanize($value)])->values()->all();
    }

    public function reportMeta(): array
    {
        return [
            'portfolio' => ['description' => 'Colocación, saldo y estado de la cartera en el período.', 'metric' => 'Saldo administrado', 'dimension' => 'Estado'],
            'collections' => ['description' => 'Resultados de gestión de cobro, recuperación y promesas.', 'metric' => 'Monto gestionado', 'dimension' => 'Resultado'],
            'applications' => ['description' => 'Demanda, aprobación y evolución de solicitudes de crédito.', 'metric' => 'Monto solicitado', 'dimension' => 'Estado'],
            'disbursements' => ['description' => 'Capital entregado y composición por forma de desembolso.', 'metric' => 'Monto desembolsado', 'dimension' => 'Forma de pago'],
            'routes' => ['description' => 'Cobertura operativa y cumplimiento de las rutas programadas.', 'metric' => 'Clientes programados', 'dimension' => 'Estado'],
            'accounting' => ['description' => 'Volumen contabilizado, borradores y trazabilidad de asientos.', 'metric' => 'Movimiento contable', 'dimension' => 'Estado'],
        ];
    }

    public function headings(string $type): array
    {
        return match ($type) {
            'portfolio' => ['Crédito', 'Fecha', 'Cliente', 'Producto', 'Vendedor', 'Principal', 'Saldo', 'Estado'],'collections' => ['Fecha', 'Cliente', 'Crédito', 'Ruta', 'Cobrador', 'Resultado', 'Monto', 'Aplicación'],'applications' => ['Solicitud', 'Fecha', 'Cliente', 'Producto', 'Vendedor', 'Solicitado', 'Aprobado', 'Estado'],'disbursements' => ['Desembolso', 'Fecha', 'Cliente', 'Préstamo', 'Forma', 'Referencia', 'Monto', 'Registró'],'routes' => ['Ruta', 'Fecha', 'Cobrador', 'Clientes', 'Estado', 'Hora', 'Código'],'accounting' => ['Asiento', 'Fecha', 'Concepto', 'Referencia', 'Debe', 'Haber', 'Estado', 'Usuario']
        };
    }

    public function row(string $type, $item): array
    {
        return match ($type) {
            'portfolio' => [$item->number, $item->disbursed_at?->format('d/m/Y'), $item->client?->full_name, $item->application?->product?->name, $item->seller?->user?->name, $item->principal, $item->outstanding_balance, $item->status],
            'collections' => [$item->recorded_at?->format('d/m/Y H:i'), $item->client?->full_name, $item->loan?->number, $item->stop?->route?->name, $item->collector?->user?->name, $item->outcome, $item->amount, $item->application_status],
            'applications' => [$item->number, $item->created_at?->format('d/m/Y'), $item->client?->full_name, $item->product?->name, $item->seller?->user?->name, $item->requested_amount, $item->approved_amount, $item->status],
            'disbursements' => [$item->number, $item->disbursed_at?->format('d/m/Y'), $item->application?->client?->full_name, $item->loan?->number, $item->payment_method, $item->reference, $item->amount, $item->disbursedBy?->name],
            'routes' => [$item->name, $item->scheduled_date?->format('d/m/Y'), $item->collector?->user?->name, $item->stops_count, $item->status, $item->starts_at, $item->code],
            'accounting' => [$item->number, $item->date?->format('d/m/Y'), $item->concept, $item->reference, $item->total_debit, $item->total_credit, $item->status, $item->user?->name],
        };
    }

    private function metricAmount(string $type, $item): float
    {
        return (float) match ($type) {
            'portfolio' => $item->outstanding_balance,
            'collections' => $item->amount,
            'applications' => $item->requested_amount,
            'disbursements' => $item->amount,
            'routes' => $item->stops_count ?? 0,
            'accounting' => $item->total_debit,
        };
    }

    private function dateValue(string $type, $item)
    {
        return match ($type) {
            'portfolio' => $item->disbursed_at,
            'collections' => $item->recorded_at,
            'applications' => $item->created_at,
            'disbursements' => $item->disbursed_at,
            'routes' => $item->scheduled_date,
            'accounting' => $item->date,
        };
    }

    private function dimensionValue(string $type, $item): string
    {
        return (string) match ($type) {
            'collections' => $item->outcome,
            'disbursements' => $item->payment_method,
            default => $item->status,
        };
    }

    private function ownerName(string $type, $item): string
    {
        return match ($type) {
            'portfolio', 'applications' => $item->seller?->user?->name ?? 'Sin gestor',
            'collections', 'routes' => $item->collector?->user?->name ?? 'Sin gestor',
            'disbursements' => $item->disbursedBy?->name ?? 'Sin responsable',
            'accounting' => $item->user?->name ?? 'Sistema',
        };
    }

    private function humanize(?string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value ?: 'Sin clasificar'));
    }
}
