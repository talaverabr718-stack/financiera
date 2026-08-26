<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guarantor extends Model
{
    protected $fillable = ['full_name', 'identity_number', 'phone', 'email', 'address'];

    public function guarantees()
    {
        return $this->hasMany(CreditGuarantor::class);
    }

    public function documents()
    {
        return $this->hasMany(GuarantorDocument::class);
    }

    public function exposureSummary(): array
    {
        $active = $this->guarantees->whereIn('status', ['approved', 'active']);
        $balance = '0.00';
        foreach ($active as $guarantee) {
            $guaranteed = (string) $guarantee->guaranteed_amount;
            $outstanding = $guarantee->loan ? (string) $guarantee->loan->outstanding_balance : $guaranteed;
            $balance = bcadd($balance, bccomp($guaranteed, $outstanding, 2) <= 0 ? $guaranteed : $outstanding, 2);
        }
        $latest = $this->guarantees->pluck('latestEvaluation')->filter()->sortByDesc('evaluated_at')->first();
        $available = $latest
            ? bcsub(bcadd((string) $latest->monthly_income, (string) $latest->other_income, 2), (string) $latest->monthly_expenses, 2)
            : '0.00';

        return [
            'active_credits' => $active->count(), 'guaranteed_balance' => $balance,
            'clients' => $active->pluck('application.client_id')->unique()->count(),
            'income' => $latest?->monthly_income ?? '0.00', 'expenses' => $latest?->monthly_expenses ?? '0.00',
            'available' => $available, 'overdue' => (bool) $latest?->has_overdue_obligations,
            'evaluated_at' => $latest?->evaluated_at,
        ];
    }
}
