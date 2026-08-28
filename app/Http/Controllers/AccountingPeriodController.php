<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountingPeriodController extends Controller
{
    public function index()
    {
        $periods = AccountingPeriod::with('closedBy')->withCount(['entries', 'entries as draft_entries_count' => fn ($query) => $query->where('status', 'draft')])->latest('starts_on')->paginate(24);

        return Inertia::render('Accounting/Periods/Index', ['periods' => $periods, 'endpoints' => ['close' => route('accounting.periods.close', '__PERIOD__'), 'reopen' => route('accounting.periods.reopen', '__PERIOD__')]]);
    }

    public function close(Request $request, AccountingPeriod $period, AuditService $audit)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($period, $data, $audit, $request) {
            $period = AccountingPeriod::lockForUpdate()->findOrFail($period->id);
            if ($period->status !== 'open') throw ValidationException::withMessages(['period' => 'El período ya está cerrado.']);
            if (JournalEntry::where('accounting_period_id', $period->id)->where('status', 'draft')->exists()) throw ValidationException::withMessages(['period' => 'Contabiliza o corrige todos los borradores antes de cerrar el período.']);
            $period->update(['status' => 'closed', 'closed_by_id' => $request->user()->id, 'closed_at' => now(), 'closing_notes' => $data['notes']]);
            $audit->record($period, 'accounting.period.closed', $request->user()->id, ['status' => 'open'], ['status' => 'closed', 'closed_at' => $period->closed_at?->toISOString()], $data['notes']);
        });

        return back()->with('success', 'Período cerrado. Sus movimientos quedaron bloqueados.');
    }

    public function reopen(Request $request, AccountingPeriod $period, AuditService $audit)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($period, $data, $audit, $request) {
            $period = AccountingPeriod::lockForUpdate()->findOrFail($period->id);
            if ($period->status !== 'closed') throw ValidationException::withMessages(['period' => 'El período ya está abierto.']);
            $before = $period->only(['status', 'closed_by_id', 'closed_at']);
            $period->update(['status' => 'open', 'closed_by_id' => null, 'closed_at' => null, 'closing_notes' => null]);
            $audit->record($period, 'accounting.period.reopened', $request->user()->id, $before, ['status' => 'open'], $data['reason']);
        });

        return back()->with('success', 'Período reabierto con trazabilidad.');
    }
}
