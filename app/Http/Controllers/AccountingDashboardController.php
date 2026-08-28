<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
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
        $monthlyTrend = collect(range(5, 0))->map(function (int $monthsAgo) use ($from) {
            $date = now()->parse($from)->subMonths($monthsAgo);
            $query = JournalEntry::posted()->whereBetween('date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()]);
            return ['label' => $date->translatedFormat('M Y'), 'movement' => (float) (clone $query)->sum('total_debit'), 'entries' => (clone $query)->count()];
        });
        $accountBalances = Account::active()->withSum(['lines as debit_total' => fn ($query) => $query->whereHas('journalEntry', fn ($entries) => $entries->posted()->whereDate('date', '<=', $to))], 'debit')->withSum(['lines as credit_total' => fn ($query) => $query->whereHas('journalEntry', fn ($entries) => $entries->posted()->whereDate('date', '<=', $to))], 'credit')->get()->map(function (Account $account) {
            $debit = (float) ($account->debit_total ?? 0); $credit = (float) ($account->credit_total ?? 0);
            $account->setAttribute('balance', $account->nature === 'debit' ? $debit - $credit : $credit - $debit); return $account;
        });
        $income = round($accountBalances->whereIn('type', ['revenue','other_income'])->sum('balance'), 2);
        $expenses = round($accountBalances->whereIn('type', ['expense','other_expense'])->sum('balance'), 2);
        $structure = [
            ['label' => 'Activos', 'value' => round($accountBalances->whereIn('type', ['asset_current','asset_non_current'])->sum('balance'), 2), 'color' => '#2585ea'],
            ['label' => 'Pasivos', 'value' => round($accountBalances->whereIn('type', ['liability_current','liability_long_term'])->sum('balance'), 2), 'color' => '#f2ae2e'],
            ['label' => 'Patrimonio', 'value' => round($accountBalances->where('type', 'equity')->sum('balance') + $income - $expenses, 2), 'color' => '#17a58c'],
        ];
        $postedCount = JournalEntry::posted()->whereBetween('date', [$from, $to])->count();
        $missingSupport = JournalEntry::posted()->whereBetween('date', [$from, $to])->whereNull('document_number')->count();
        $control = ['net_result' => round($income - $expenses, 2), 'income' => $income, 'expenses' => $expenses, 'missing_support' => $missingSupport, 'support_rate' => $postedCount ? round(($postedCount - $missingSupport) / $postedCount * 100, 1) : 100, 'drafts' => JournalEntry::where('status', 'draft')->count()];
        $topAccounts = JournalEntryLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')->whereIn('journal_entries.status', ['posted','reversed'])->whereBetween('journal_entries.date', [$from, $to])->groupBy('accounts.id', 'accounts.code', 'accounts.name')->orderByRaw('SUM(journal_entry_lines.debit + journal_entry_lines.credit) DESC')->limit(5)->get(['accounts.id','accounts.code','accounts.name'])->map(function ($account) use ($from, $to) {
            $account->movement = (float) JournalEntryLine::where('account_id', $account->id)->whereHas('journalEntry', fn ($entries) => $entries->posted()->whereBetween('date', [$from, $to]))->selectRaw('COALESCE(SUM(debit + credit),0) total')->value('total'); return $account;
        });

        return Inertia::render('Accounting/Dashboard', compact('month', 'summary', 'recent', 'monthlyTrend', 'structure', 'control', 'topAccounts') + ['period' => AccountingPeriod::whereDate('starts_on','<=',$from)->whereDate('ends_on','>=',$from)->first(), 'links' => ['accounts'=>route('accounting.accounts.index'),'entries'=>route('accounting.entries.index'),'createEntry'=>route('accounting.entries.create'),'journal'=>route('accounting.journal'),'ledger'=>route('accounting.ledger'),'trial'=>route('accounting.trial'),'balanceSheet'=>route('accounting.balance-sheet'),'incomeStatement'=>route('accounting.income-statement'),'periods'=>route('accounting.periods.index'),'costCenters'=>route('accounting.cost-centers.index')]]);
    }
}
