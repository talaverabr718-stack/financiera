<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(private DocumentSequenceService $sequences) {}

    public function create(array $data, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($data, $userId) {
            $entry = JournalEntry::create(['number' => $this->sequences->next('journal_entry', 'ASI-'), 'date' => $data['date'], 'concept' => $data['concept'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'status' => 'draft', 'user_id' => $userId, 'total_debit' => '0.00', 'total_credit' => '0.00']);
            $debit = '0.00';
            $credit = '0.00';
            foreach ($data['lines'] as $line) {
                $d = (string) ($line['debit'] ?? 0);
                $c = (string) ($line['credit'] ?? 0);
                $entry->lines()->create(['account_id' => $line['account_id'], 'detail' => $line['detail'] ?? null, 'debit' => $d, 'credit' => $c]);
                $debit = bcadd($debit, $d, 2);
                $credit = bcadd($credit, $c, 2);
            }$entry->update(['total_debit' => $debit, 'total_credit' => $credit]);

            return $entry;
        });
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            $entry = JournalEntry::with('lines.account')->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['entry' => 'Solo se contabilizan asientos en borrador.']);
            }if ($entry->lines->count() < 2 || bccomp((string) $entry->total_debit, (string) $entry->total_credit, 2) !== 0 || bccomp((string) $entry->total_debit, '0', 2) <= 0) {
                throw ValidationException::withMessages(['entry' => 'El asiento debe estar balanceado y contener movimientos.']);
            }if ($entry->lines->contains(fn ($line) => ! $line->account->is_active || ! $line->account->is_postable)) {
                throw ValidationException::withMessages(['entry' => 'El asiento contiene cuentas inactivas o no imputables.']);
            }$entry->update(['status' => 'posted', 'posted_at' => now()]);

            return $entry;
        });
    }

    public function reverse(JournalEntry $entry, string $reason, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason, $userId) {
            $entry = JournalEntry::with('lines')->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== 'posted' || $entry->reversal()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Solo se reversan asientos contabilizados que no hayan sido reversados.']);
            }$reversal = JournalEntry::create(['number' => $this->sequences->next('journal_entry', 'ASI-'), 'date' => today(), 'concept' => 'Reversión de '.$entry->number, 'reference' => $entry->number, 'notes' => $reason, 'status' => 'posted', 'reversal_of_id' => $entry->id, 'user_id' => $userId, 'total_debit' => $entry->total_credit, 'total_credit' => $entry->total_debit, 'posted_at' => now()]);
            foreach ($entry->lines as $line) {
                $reversal->lines()->create(['account_id' => $line->account_id, 'detail' => 'Reversión: '.($line->detail ?: $entry->concept), 'debit' => $line->credit, 'credit' => $line->debit]);
            }$entry->update(['status' => 'reversed', 'reversed_at' => now()]);

            return $reversal;
        });
    }
}
