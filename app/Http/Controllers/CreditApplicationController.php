<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditApplicationRequest;
use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Guarantor;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\CreditApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditApplicationController extends Controller
{
    public function __construct(private CreditApplicationService $applications) {}

    public function index(Request $request)
    {
        $applications = CreditApplication::with(['client', 'seller.user', 'product'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$request->search.'%')->orWhereHas('client', fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%'))))->latest()->paginate(15)->withQueryString();

        return view('applications.index', compact('applications'));
    }

    public function create()
    {
        return $this->form(new CreditApplication);
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

        return view('applications.show', compact('application'));
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
        DB::transaction(fn () => CreditApplication::lockForUpdate()->findOrFail($application->id)->update($data + ['decided_by' => $decision ? (auth()->id() ?? User::query()->value('id')) : $application->decided_by, 'decided_at' => $decision ? now() : $application->decided_at]));

        return back()->with('success', 'Estado de la solicitud actualizado.');
    }

    private function form(CreditApplication $application)
    {
        $application->load('guarantees.latestEvaluation');
        $guarantors = Guarantor::with(['guarantees.application.client', 'guarantees.loan', 'guarantees.latestEvaluation'])->orderBy('full_name')->get();
        return view('applications.form', ['application' => $application, 'clients' => Client::where('status', 'active')->orderBy('full_name')->get(), 'sellers' => SellerProfile::with('user')->where('status', 'active')->whereJsonContains('capabilities', 'credit_origination')->get(), 'products' => CreditProduct::where('is_active', true)->orderBy('name')->get(), 'guarantors' => $guarantors]);
    }

}
