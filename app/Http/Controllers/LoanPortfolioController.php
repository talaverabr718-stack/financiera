<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\SellerProfile;
use App\Services\DelinquencyTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LoanPortfolioController extends Controller
{
    public function index(Request $request)
    {
        $base = Loan::query();
        $summary = [
            'total' => (clone $base)->sum('principal'),
            'outstanding' => (clone $base)->selectRaw('COALESCE(SUM(principal_balance + interest_balance + fee_balance), 0) as total')->value('total'),
            'active' => (clone $base)->whereIn('status', ['active', 'delinquent'])->count(),
            'delinquent' => (clone $base)->where('status', 'delinquent')->count(),
        ];
        $loans = Loan::with(['client.activeAssignment.seller.user', 'seller.user', 'application.product'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$request->search.'%')->orWhereHas('client', fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%')->orWhere('identity_number', 'like', '%'.$request->search.'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('seller'), fn ($q) => $q->where('seller_id', $request->integer('seller')))
            ->latest('disbursed_at')->paginate(15)->withQueryString();
        $sellers = SellerProfile::with('user')->where('status', 'active')
            ->whereHas('portfolioAssignments', fn ($q) => $q->whereNull('ended_at'))->orderBy('code')->get();

        return Inertia::render('Loans/Index', [
            'loans' => $loans,
            'summary' => $summary,
            'sellers' => $sellers,
            'filters' => $request->only('search', 'status', 'seller'),
            'endpoints' => ['index' => route('loans.index')],
        ]);
    }

    public function show(Loan $loan)
    {
        $loan->load(['client.activeAssignment.seller.user', 'seller.user', 'application.product', 'disbursement.disbursedBy', 'installments', 'payments.allocations.installment', 'collectionRecords.collector.user', 'collectionRecords.recordedBy', 'guarantees.guarantor', 'activeDelinquencyCase.items']);
        $timeline = collect([
            ['date' => $loan->application->created_at, 'type' => 'Solicitud', 'title' => $loan->application->number, 'description' => 'Solicitud registrada'],
            ['date' => $loan->application->decided_at, 'type' => 'Aprobación', 'title' => 'Crédito aprobado', 'description' => $loan->currency.' '.number_format((float) $loan->principal, 2)],
            ['date' => $loan->disbursement?->created_at ?? $loan->disbursed_at, 'type' => 'Desembolso', 'title' => $loan->disbursement?->number ?? 'Desembolso', 'description' => $loan->disbursement?->payment_method ?? 'Préstamo activado'],
        ])->concat($loan->installments->map(fn ($item) => ['date' => $item->due_date, 'type' => 'Cuota', 'title' => 'Cuota '.$item->number, 'description' => 'Estado: '.$item->status]))
            ->concat($loan->payments->map(fn ($item) => ['date' => $item->received_at, 'type' => 'Pago', 'title' => $item->receipt_number, 'description' => $loan->currency.' '.number_format((float) $item->amount, 2).' · '.$item->status]))
            ->concat($loan->collectionRecords->map(fn ($item) => ['date' => $item->recorded_at, 'type' => 'Cobranza', 'title' => $item->outcome, 'description' => ($item->amount ? $loan->currency.' '.number_format((float) $item->amount, 2).' · ' : '').($item->notes ?: 'Gestión registrada')]))
            ->filter(fn ($item) => $item['date'])->sortByDesc('date')->values();
        $delinquency = app(DelinquencyTrackingService::class)->summarizeLoan($loan);

        return view('loans.show', compact('loan', 'timeline', 'delinquency'));
    }

    public function updateStatus(Request $request, Loan $loan)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'delinquent', 'paid'])]]);
        if ($loan->status === 'paid') {
            return back()->with('success', 'El crédito ya está pagado y su estado se conserva como registro financiero final.');
        }
        if ($data['status'] === 'paid' && bccomp($loan->outstanding_balance, '0.00', 2) !== 0) {
            return back()->withErrors(['status' => 'No se puede marcar como pagado mientras exista saldo pendiente.']);
        }
        DB::transaction(function () use ($loan, $data): void {
            Loan::lockForUpdate()->findOrFail($loan->id)->update(['status' => $data['status']]);
        });

        return back()->with('success', 'Estado del crédito actualizado correctamente.');
    }
}
