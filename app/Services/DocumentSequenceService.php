<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    public function next(string $key, string $prefix, int $padding = 6): string
    {
        return DB::transaction(function () use ($key, $prefix, $padding) {
            DB::table('document_sequences')->insertOrIgnore(['key' => $key, 'prefix' => $prefix, 'next_number' => 1, 'padding' => $padding, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
            $number = (int) $sequence->next_number;
            $candidate = $sequence->prefix.str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);

            $targets = [
                'client' => ['clients', 'code'],
                'credit_application' => ['credit_applications', 'number'],
                'loan' => ['loans', 'number'],
                'loan_disbursement' => ['loan_disbursements', 'number'],
                'payment' => ['payments', 'receipt_number'],
                'journal_entry' => ['journal_entries', 'number'],
                'delinquency_case' => ['delinquency_cases', 'code'],
            ];

            if (isset($targets[$key])) {
                [$table, $column] = $targets[$key];
                while (DB::table($table)->where($column, $candidate)->exists()) {
                    $number++;
                    $candidate = $sequence->prefix.str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);
                }
            }

            DB::table('document_sequences')->where('key', $key)->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $candidate;
        });
    }
}
