<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(private DocumentSequenceService $sequences, private AuditService $audit) {}

    public function create(array $data, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($data, $userId) {
            $period = $this->periodFor($data['date']);
            $entry = JournalEntry::create(['number' => $this->sequences->next('journal_entry', 'ASI-'), 'date' => $data['date'], 'accounting_period_id' => $period->id, 'concept' => $data['concept'], 'reference' => $data['reference'] ?? null, 'document_type' => $data['document_type'] ?? null, 'document_number' => $data['document_number'] ?? null, 'counterparty_name' => $data['counterparty_name'] ?? null, 'counterparty_ruc' => $data['counterparty_ruc'] ?? null, 'notes' => $data['notes'] ?? null, 'status' => 'draft', 'user_id' => $userId, 'total_debit' => '0.00', 'total_credit' => '0.00']);
            $debit = '0.00';
            $credit = '0.00';
            foreach ($data['lines'] as $line) {
                $d = (string) ($line['debit'] ?? 0);
                $c = (string) ($line['credit'] ?? 0);
                $entry->lines()->create(['account_id' => $line['account_id'], 'cost_center_id' => $line['cost_center_id'] ?? null, 'detail' => $line['detail'] ?? null, 'debit' => $d, 'credit' => $c]);
                $debit = bcadd($debit, $d, 2);
                $credit = bcadd($credit, $c, 2);
            }$entry->update(['total_debit' => $debit, 'total_credit' => $credit]);

            $this->audit->record($entry, 'accounting.entry.created', $userId, [], $entry->only(['number','date','concept','document_type','document_number','total_debit','total_credit']));
            return $entry;
        });
    }

    public function post(JournalEntry $entry, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId) {
            $entry = JournalEntry::with(['lines.account','accountingPeriod'])->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['entry' => 'Solo se contabilizan asientos en borrador.']);
            }if ($entry->lines->count() < 2 || bccomp((string) $entry->total_debit, (string) $entry->total_credit, 2) !== 0 || bccomp((string) $entry->total_debit, '0', 2) <= 0) {
                throw ValidationException::withMessages(['entry' => 'El asiento debe estar balanceado y contener movimientos.']);
            }if ($entry->lines->contains(fn ($line) => ! $line->account->is_active || ! $line->account->is_postable)) {
                throw ValidationException::withMessages(['entry' => 'El asiento contiene cuentas inactivas o no imputables.']);
            }if ($entry->accountingPeriod?->status !== 'open') {
                throw ValidationException::withMessages(['entry' => 'El período contable está cerrado.']);
            }$entry->update(['status' => 'posted', 'posted_by_id' => $userId, 'posted_at' => now()]);

            $this->audit->record($entry, 'accounting.entry.posted', $userId, ['status' => 'draft'], ['status' => 'posted', 'posted_at' => $entry->posted_at?->toISOString()]);

            return $entry;
        });
    }

    public function reverse(JournalEntry $entry, string $reason, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason, $userId) {
            $entry = JournalEntry::with(['lines','accountingPeriod'])->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== 'posted' || $entry->reversal()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Solo se reversan asientos contabilizados que no hayan sido reversados.']);
            }$period = $this->periodFor(today());
            $reversal = JournalEntry::create(['number' => $this->sequences->next('journal_entry', 'ASI-'), 'date' => today(), 'accounting_period_id' => $period->id, 'concept' => 'Reversión de '.$entry->number, 'reference' => $entry->number, 'document_type' => 'internal_support', 'document_number' => $entry->number, 'counterparty_name' => $entry->counterparty_name, 'counterparty_ruc' => $entry->counterparty_ruc, 'notes' => $reason, 'status' => 'posted', 'reversal_of_id' => $entry->id, 'user_id' => $userId, 'posted_by_id' => $userId, 'total_debit' => $entry->total_credit, 'total_credit' => $entry->total_debit, 'posted_at' => now()]);
            foreach ($entry->lines as $line) {
                $reversal->lines()->create(['account_id' => $line->account_id, 'cost_center_id' => $line->cost_center_id, 'detail' => 'Reversión: '.($line->detail ?: $entry->concept), 'debit' => $line->credit, 'credit' => $line->debit]);
            }$entry->update(['status' => 'reversed', 'reversed_at' => now()]);

            $this->audit->record($entry, 'accounting.entry.reversed', $userId, ['status' => 'posted'], ['status' => 'reversed', 'reversal_id' => $reversal->id], $reason);

            return $reversal;
        });
    }

    private function periodFor(string|Carbon $date): AccountingPeriod
    {
        $day = Carbon::parse($date);
        $period = AccountingPeriod::whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->lockForUpdate()->first();
        if (! $period) {
            $period = AccountingPeriod::create(['name' => $day->translatedFormat('F Y'), 'starts_on' => $day->copy()->startOfMonth(), 'ends_on' => $day->copy()->endOfMonth(), 'status' => 'open']);
        }
        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['date' => 'No se permiten movimientos en un período contable cerrado.']);
        }
        return $period;
    }
}
