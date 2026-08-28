<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $month = $request->month ?: today()->format('Y-m');
        $from = $month.'-01';
        $to = now()->parse($from)->endOfMonth()->format('Y-m-d');
        $posted = JournalEntry::posted()->whereBetween('date', [$from, $to]);
        $summary = ['debit' => (clone $posted)->sum('total_debit'), 'credit' => (clone $posted)->sum('total_credit'), 'posted' => (clone $posted)->count(), 'draft' => JournalEntry::where('status', 'draft')->count(), 'accounts' => Account::active()->count(), 'reversed' => JournalEntry::where('status', 'reversed')->whereBetween('date', [$from, $to])->count()];
        $recent = JournalEntry::with('user')->latest('date')->latest('id')->take(8)->get();

        return Inertia::render('Accounting/Dashboard', compact('month', 'summary', 'recent') + ['links' => ['accounts'=>route('accounting.accounts.index'),'entries'=>route('accounting.entries.index'),'createEntry'=>route('accounting.entries.create'),'journal'=>route('accounting.journal'),'ledger'=>route('accounting.ledger'),'trial'=>route('accounting.trial')]]);
    }
}
