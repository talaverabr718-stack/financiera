<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisburseCreditApplicationRequest;
use App\Models\CreditApplication;
use App\Models\User;
use App\Services\LoanDisbursementService;

class LoanDisbursementController extends Controller
{
    public function __construct(private LoanDisbursementService $disbursements) {}

    public function store(DisburseCreditApplicationRequest $request, CreditApplication $application)
    {
        $userId = auth()->id() ?? User::query()->value('id');
        abort_unless($userId, 422, 'No existe un usuario habilitado para registrar el desembolso.');

        $disbursement = $this->disbursements->disburse($application, $request->validated(), $userId);

        return redirect()->route('loans.show', $disbursement->loan_id)
            ->with('success', "Desembolso {$disbursement->number} registrado correctamente.");
    }
}
