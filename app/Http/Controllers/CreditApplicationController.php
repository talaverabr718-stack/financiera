<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditApplicationRequest;
use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Services\CreditApplicationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CreditApplicationController extends Controller
{
    public function __construct(private CreditApplicationService $applications) {}

    public function index(Request $request)
    {
        $applications = CreditApplication::with(['client', 'seller.user', 'product'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$request->search.'%')->orWhereHas('client', fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%'))))->latest()->paginate(15)->withQueryString();

        return Inertia::render('Applications/Index', [
            'applications' => $applications,
            'filters' => $request->only('search', 'status'),
            'endpoints' => ['index' => route('applications.index'), 'create' => route('applications.create')],
        ]);
    }

    public function create(Request $request)
    {
        $application = new CreditApplication;
        if ($request->filled('client_id')) {
            $client = Client::with('activeAssignment')->find($request->integer('client_id'));
            if ($client?->canOriginateNewCredit()) {
                $application->client_id = $client->id;
                $application->seller_id = $client->activeAssignment?->seller_id;
            }
        }

        return $this->form($application);
    }

    public function edit(CreditApplication $application)
    {
        return $this->form($application);
    }

    public function store(CreditApplicationRequest $request)
    {
        $application = $this->applications->create($request->validated());

        return redirect()->route('applications.show', $application)->with('success', 'Solicitud registrada.');
    }

    public function update(CreditApplicationRequest $request, CreditApplication $application)
    {
        abort_if(in_array($application->status, ['disbursed', 'cancelled'], true), 422, 'Esta solicitud ya no admite edición directa.');
        $this->applications->update($application, $request->validated());

        return redirect()->route('applications.show', $application)->with('success', 'Solicitud actualizada.');
    }

    public function show(CreditApplication $application)
    {
        $application->load(['client', 'seller.user', 'product', 'decidedBy', 'loan', 'disbursement.disbursedBy', 'guarantees.guarantor', 'guarantees.latestEvaluation', 'guarantees.loan']);

        return Inertia::render('Applications/Show', [
            'application' => [
                ...$application->only(['id', 'number', 'status', 'requested_amount', 'approved_amount', 'currency', 'purpose', 'applied_on', 'term', 'installment_amount', 'payment_frequency', 'interest_rate', 'interest_method', 'requires_guarantor', 'decision_reason']),
                'client_name' => $application->client->full_name,
                'product_name' => $application->product->name,
                'seller_name' => $application->seller->user->name,
                'applied_on' => $application->applied_on?->toDateString(),
                'proposed_first_payment_date' => $application->proposed_first_payment_date?->toDateString(),
                'approved_at' => $application->approved_at?->toISOString(),
                'estimated_last_payment_date' => $application->estimated_last_payment_date?->toDateString(),
                'disbursement' => $application->disbursement?->only(['number', 'amount', 'disbursed_at']),
                'disbursement_key' => (string) Str::uuid(),
                'today' => today()->toDateString(),
            ],
            'guarantees' => $application->guarantees->map(fn ($guarantee) => [
                'id' => $guarantee->id,
                'name' => $guarantee->guarantor->full_name,
                'relationship' => $guarantee->relationship,
                'amount' => $guarantee->guaranteed_amount,
                'status' => $guarantee->status,
                'income' => $guarantee->latestEvaluation?->monthly_income,
                'expenses' => $guarantee->latestEvaluation?->monthly_expenses,
                'overdue' => (bool) $guarantee->latestEvaluation?->has_overdue_obligations,
                'decision_url' => route('guarantees.decision', $guarantee),
            ])->values(),
            'statusLabels' => ['draft' => 'Borrador', 'submitted' => 'Enviada', 'review' => 'En revisión', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'cancelled' => 'Cancelada', 'disbursed' => 'Desembolsada'],
            'endpoints' => [
                'index' => route('applications.index'),
                'edit' => route('applications.edit', $application),
                'status' => route('applications.status', $application),
                'disburse' => route('applications.disburse', $application),
                'loan' => $application->loan ? route('loans.show', $application->loan) : null,
            ],
            'csrf' => csrf_token(),
        ]);
    }

    public function status(Request $request, CreditApplication $application)
    {
        if (in_array($application->status, ['disbursed', 'cancelled'], true)) {
            return redirect()->route('applications.show', $application)
                ->with('success', $application->status === 'disbursed'
                    ? 'La solicitud ya fue desembolsada y se conserva en modo de solo lectura. Consulta el préstamo para continuar su gestión.'
                    : 'La solicitud está cancelada y se conserva en modo de solo lectura.');
        }
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'submitted', 'review', 'approved', 'rejected', 'cancelled'])], 'decision_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'], 'approved_amount' => ['nullable', 'required_if:status,approved', 'decimal:0,2', 'gt:0']]);
        if ($data['status'] === 'approved' && $application->requires_guarantor && (! $application->guarantees()->exists() || $application->guarantees()->where('status', '!=', 'approved')->exists())) {
            throw ValidationException::withMessages(['status' => 'Todos los fiadores requeridos deben estar aprobados antes de aprobar la solicitud.']);
        }
        $decision = in_array($data['status'], ['approved', 'rejected'], true);
        DB::transaction(function () use ($application, $data, $decision): void {
            $locked = CreditApplication::lockForUpdate()->findOrFail($application->id);
            $approval = $data['status'] === 'approved';
            $approvedAt = $approval ? ($locked->approved_at ?? now()) : $locked->approved_at;
            $firstPaymentDate = $approval ? $this->firstInstallmentAfter($locked, $approvedAt) : $locked->proposed_first_payment_date;
            if ($approval) {
                $locked->proposed_first_payment_date = $firstPaymentDate;
            }
            $lastPayment = $approval ? $this->estimateLastPaymentDate($locked, $approvedAt) : $locked->estimated_last_payment_date;

            $locked->update($data + [
                'decided_by' => $decision ? auth()->id() : $locked->decided_by,
                'decided_at' => $decision ? now() : $locked->decided_at,
                'approved_at' => $approvedAt,
                'estimated_last_payment_date' => $lastPayment,
                'proposed_first_payment_date' => $firstPaymentDate,
            ]);
        });

        if ($request->expectsJson()) {
            $fresh = $application->fresh();

            return response()->json([
                'message' => 'Estado de la solicitud actualizado.',
                'application' => [
                    ...$fresh->only(['status', 'approved_amount', 'decision_reason']),
                    'approved_at' => $fresh->approved_at?->toISOString(),
                    'proposed_first_payment_date' => $fresh->proposed_first_payment_date?->toDateString(),
                    'estimated_last_payment_date' => $fresh->estimated_last_payment_date?->toDateString(),
                ],
            ]);
        }

        return back()->with('success', 'Estado de la solicitud actualizado.');
    }

    private function firstInstallmentAfter(CreditApplication $application, Carbon $approvedAt): Carbon
    {
        $existing = $application->proposed_first_payment_date?->copy()->startOfDay();
        $approvalDay = $approvedAt->copy()->startOfDay();
        if ($existing && $existing->gt($approvalDay)) {
            return $existing;
        }

        return match ($application->payment_frequency) {
            'daily' => $approvalDay->addDay(),
            'weekly' => $approvalDay->addWeek(),
            'biweekly' => $approvalDay->addDays(14),
            'monthly' => $approvalDay->addMonthNoOverflow(),
        };
    }

    private function estimateLastPaymentDate(CreditApplication $application, Carbon $approvedAt): Carbon
    {
        $date = ($application->proposed_first_payment_date ?? $this->firstInstallmentAfter($application, $approvedAt))->copy()->startOfDay();
        $remainingPayments = max(0, $application->term - 1);

        return match ($application->payment_frequency) {
            'daily' => $date->addDays($remainingPayments),
            'weekly' => $date->addWeeks($remainingPayments),
            'biweekly' => $date->addDays($remainingPayments * 14),
            'monthly' => $date->addMonthsNoOverflow($remainingPayments),
        };
    }

    private function form(CreditApplication $application)
    {
        $application->load('guarantees.latestEvaluation');
        $guarantors = Guarantor::with(['guarantees.application.client', 'guarantees.loan', 'guarantees.latestEvaluation'])->orderBy('full_name')->get();

        return Inertia::render('Applications/Form', [
            'application' => $application,
            'clients' => Client::where('status', 'active')->withCount(['loans as open_loans_count' => fn ($query) => $query->whereIn('status', Loan::COLLECTIBLE_STATUSES)])->orderBy('full_name')->get(),
            'sellers' => SellerProfile::with('user')->where('status', 'active')->whereJsonContains('capabilities', 'credit_origination')->get(),
            'products' => CreditProduct::where('is_active', true)->orderBy('name')->get(),
            'guarantors' => $guarantors,
            'editing' => $application->exists,
            'endpoints' => [
                'index' => route('applications.index'),
                'save' => $application->exists ? route('applications.update', $application) : route('applications.store'),
            ],
        ]);
    }
}
