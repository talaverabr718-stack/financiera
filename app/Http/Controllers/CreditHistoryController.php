<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Payment;
use App\Support\OperationalMesa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $clients = Client::query()
            ->with(['activeAssignment.seller.user'])
            ->withCount([
                'loans',
                'loans as open_loans_count' => fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES),
                'payments',
            ])
            ->with(['loans' => fn ($query) => $query->latest('id')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(fn ($query) => $query
                    ->where('full_name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('identity_number', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->input('status') === 'open', fn ($query) => $query->whereHas('loans', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES)))
            ->when($request->input('status') === 'unlocked', fn ($query) => $query->whereDoesntHave('loans', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES)))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'code' => $client->code,
                'full_name' => $client->full_name,
                'identity_number' => $client->identity_number,
                'loans_count' => $client->loans_count,
                'payments_count' => $client->payments_count,
                'latest_loan' => $client->loans->first()?->only('id', 'number', 'status'),
                'can_originate_new_credit' => (int) $client->open_loans_count === 0,
            ]);

        $open = Client::query()->whereHas('loans', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES))->count();
        $unlocked = Client::query()->whereDoesntHave('loans', fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES))->count();
        $withHistory = Client::query()->has('loans')->count();
        $situation = $withHistory === 0
            ? 'Todavía no hay historial crediticio para mostrar.'
            : "{$withHistory} cliente".($withHistory === 1 ? '' : 's')." con créditos · {$open} con crédito vigente · {$unlocked} desbloqueado".($unlocked === 1 ? '' : 's')." para un nuevo crédito";

        return Inertia::render('CreditHistory/Index', [
            'clients' => $clients,
            'board' => [
                'briefing' => [
                    'title' => 'Historial crediticio',
                    'date_label' => OperationalMesa::dateLabel(),
                    'situation' => $situation,
                ],
                'stats' => ['total' => $open + $unlocked],
                'mix' => [
                    ['key' => 'open', 'tone' => 'info', 'label' => 'Crédito vigente', 'value' => $open],
                    ['key' => 'unlocked', 'tone' => 'ok', 'label' => 'Nuevo crédito', 'value' => $unlocked],
                ],
                'growth' => OperationalMesa::monthlyGrowth(Loan::query()->whereNotNull('disbursed_at'), 'disbursed_at'),
            ],
            'filters' => $request->only('search', 'status'),
            'endpoints' => ['index' => route('credit-history.index')],
        ]);
    }

    public function show(Client $client): Response
    {
        $client->load(['activeAssignment.seller.user']);
        $loans = $client->loans()
            ->with(['application.product', 'payments' => fn ($query) => $query->latest('received_at'), 'seller.user'])
            ->latest('disbursed_at')
            ->latest('id')
            ->get()
            ->map(fn (Loan $loan) => [
                'id' => $loan->id,
                'number' => $loan->number,
                'status' => $loan->status,
                'currency' => $loan->currency,
                'principal' => $loan->principal,
                'outstanding' => $loan->outstanding_balance,
                'mora' => (string) $loan->delinquency_balance,
                'disbursed_at' => $loan->disbursed_at?->format('Y-m-d'),
                'product' => $loan->application?->product?->name,
                'seller' => $loan->seller?->user?->name,
                'is_open' => $loan->isCollectible(),
                'show_url' => route('loans.show', $loan),
                'payments' => $loan->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'received_at' => $payment->received_at?->format('d/m/Y H:i'),
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'method' => $payment->payment_method,
                    'status' => $payment->status,
                    'reference' => $payment->reference,
                ])->values(),
            ]);

        return Inertia::render('CreditHistory/Show', [
            'client' => [
                'id' => $client->id,
                'code' => $client->code,
                'full_name' => $client->full_name,
                'identity_number' => $client->identity_number,
                'phone' => $client->phone,
                'seller' => $client->activeAssignment?->seller?->user?->name,
                'can_originate_new_credit' => $client->canOriginateNewCredit(),
                'credits_count' => $loans->count(),
                'paid_credits_count' => $loans->where('status', 'paid')->count(),
            ],
            'loans' => $loans,
            'endpoints' => [
                'index' => route('credit-history.index'),
                'new_credit' => route('applications.create', ['client_id' => $client->id]),
            ],
        ]);
    }
}
