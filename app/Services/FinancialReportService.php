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

class FinancialReportService
{
    public const TYPES = ['portfolio'=>'Cartera','collections'=>'Cobranza','applications'=>'Solicitudes','disbursements'=>'Desembolsos','routes'=>'Rutas','accounting'=>'Contabilidad'];

    public function query(string $type, string $from, string $to, Request $request): Builder
    {
        $query = match ($type) {
            'portfolio' => Loan::with(['client','seller.user','application.product'])->whereBetween('disbursed_at',[$from,$to]),
            'collections' => CollectionRecord::with(['client','loan','collector.user','stop.route'])->whereBetween('recorded_at',[$from.' 00:00:00',$to.' 23:59:59']),
            'applications' => CreditApplication::with(['client','seller.user','product'])->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59']),
            'disbursements' => LoanDisbursement::with(['application.client','loan','disbursedBy'])->whereBetween('disbursed_at',[$from,$to]),
            'routes' => CollectionRoute::with(['collector.user'])->withCount('stops')->whereBetween('scheduled_date',[$from,$to]),
            'accounting' => JournalEntry::with('user')->whereBetween('date',[$from,$to]),
        };
        if ($request->filled('status') && in_array($type,['portfolio','applications','routes','accounting'],true)) $query->where('status',$request->status);
        if ($request->filled('client') && in_array($type,['portfolio','collections','applications'],true)) $query->where('client_id',$request->integer('client'));
        if ($request->filled('seller')) {
            $column = in_array($type,['portfolio','applications'],true)?'seller_id':($type==='routes'?'collector_id':($type==='collections'?'collector_id':null));
            if ($column) $query->where($column,$request->integer('seller'));
        }
        return $query;
    }

    public function summary(string $type, Builder $query): array
    {
        return match ($type) {
            'portfolio' => ['count'=>(clone $query)->count(),'primary'=>(clone $query)->sum('principal'),'secondary'=>(clone $query)->selectRaw('COALESCE(SUM(principal_balance+interest_balance+fee_balance),0) total')->value('total'),'primary_label'=>'Monto colocado','secondary_label'=>'Saldo pendiente'],
            'collections' => ['count'=>(clone $query)->count(),'primary'=>(clone $query)->where('outcome','collected')->sum('amount'),'secondary'=>(clone $query)->where('outcome','promise')->count(),'primary_label'=>'Monto cobrado','secondary_label'=>'Promesas'],
            'applications' => ['count'=>(clone $query)->count(),'primary'=>(clone $query)->sum('requested_amount'),'secondary'=>(clone $query)->sum('approved_amount'),'primary_label'=>'Monto solicitado','secondary_label'=>'Monto aprobado'],
            'disbursements' => ['count'=>(clone $query)->count(),'primary'=>(clone $query)->sum('amount'),'secondary'=>(clone $query)->where('payment_method','cash')->sum('amount'),'primary_label'=>'Total desembolsado','secondary_label'=>'Entregado en efectivo'],
            'routes' => ['count'=>(clone $query)->count(),'primary'=>(clone $query)->withCount('stops')->get()->sum('stops_count'),'secondary'=>(clone $query)->where('status','completed')->count(),'primary_label'=>'Clientes programados','secondary_label'=>'Rutas finalizadas'],
            'accounting' => ['count'=>(clone $query)->count(),'primary'=>(clone $query)->where('status','posted')->sum('total_debit'),'secondary'=>(clone $query)->where('status','draft')->count(),'primary_label'=>'Movimientos contabilizados','secondary_label'=>'Borradores'],
        };
    }

    public function headings(string $type): array
    {
        return match($type){'portfolio'=>['Crédito','Fecha','Cliente','Producto','Vendedor','Principal','Saldo','Estado'],'collections'=>['Fecha','Cliente','Crédito','Ruta','Cobrador','Resultado','Monto','Aplicación'],'applications'=>['Solicitud','Fecha','Cliente','Producto','Vendedor','Solicitado','Aprobado','Estado'],'disbursements'=>['Desembolso','Fecha','Cliente','Préstamo','Forma','Referencia','Monto','Registró'],'routes'=>['Ruta','Fecha','Cobrador','Clientes','Estado','Hora','Código'],'accounting'=>['Asiento','Fecha','Concepto','Referencia','Debe','Haber','Estado','Usuario']};
    }

    public function row(string $type, $item): array
    {
        return match($type){
            'portfolio'=>[$item->number,$item->disbursed_at?->format('d/m/Y'),$item->client->full_name,$item->application->product->name,$item->seller->user->name,$item->principal,$item->outstanding_balance,$item->status],
            'collections'=>[$item->recorded_at->format('d/m/Y H:i'),$item->client->full_name,$item->loan?->number,$item->stop->route->name,$item->collector->user->name,$item->outcome,$item->amount,$item->application_status],
            'applications'=>[$item->number,$item->created_at->format('d/m/Y'),$item->client->full_name,$item->product->name,$item->seller->user->name,$item->requested_amount,$item->approved_amount,$item->status],
            'disbursements'=>[$item->number,$item->disbursed_at->format('d/m/Y'),$item->application->client->full_name,$item->loan->number,$item->payment_method,$item->reference,$item->amount,$item->disbursedBy->name],
            'routes'=>[$item->name,$item->scheduled_date->format('d/m/Y'),$item->collector->user->name,$item->stops_count,$item->status,$item->starts_at,$item->code],
            'accounting'=>[$item->number,$item->date->format('d/m/Y'),$item->concept,$item->reference,$item->total_debit,$item->total_credit,$item->status,$item->user->name],
        };
    }
}
