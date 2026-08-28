<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingReportController extends Controller
{
    public function journal(Request $request)
    {
        $from = $request->date_from ?: today()->startOfMonth()->format('Y-m-d');
        $to = $request->date_to ?: today()->format('Y-m-d');
        $entries = JournalEntry::posted()->with(['lines.account', 'user'])->whereBetween('date', [$from, $to])->orderBy('date')->paginate(20)->withQueryString();
        $journalSummary = ['entries' => JournalEntry::posted()->whereBetween('date', [$from, $to])->count(), 'movement' => (float) JournalEntry::posted()->whereBetween('date', [$from, $to])->sum('total_debit'), 'reversals' => JournalEntry::where('status', 'reversed')->whereBetween('date', [$from, $to])->count()];

        return Inertia::render('Accounting/Reports/Index', compact('entries','from','to','journalSummary') + ['report'=>'journal']);
    }

    public function ledger(Request $request)
    {
        $from = $request->date_from ?: today()->startOfMonth()->format('Y-m-d');
        $to = $request->date_to ?: today()->format('Y-m-d');
        $accounts = Account::active()->orderBy('code')->get();
        $account = $request->account ? Account::find($request->integer('account')) : null;
        $lines = collect();
        $opening = '0.00';
        if ($account) {
            $opening = $this->balance($account, JournalEntryLine::where('account_id', $account->id)->whereHas('journalEntry', fn ($q) => $q->posted()->whereDate('date', '<', $from)));
            $running = (float) $opening;
            $lines = JournalEntryLine::with('journalEntry')->where('account_id', $account->id)->whereHas('journalEntry', fn ($q) => $q->posted()->whereBetween('date', [$from, $to]))->get()->sortBy(fn ($line) => $line->journalEntry->date->format('Y-m-d').str_pad($line->journal_entry_id, 10, '0', STR_PAD_LEFT))->values()->map(function ($line) use (&$running, $account) {
                $movement = $account->nature === 'debit' ? (float) $line->debit - (float) $line->credit : (float) $line->credit - (float) $line->debit;
                $running = round($running + $movement, 2); $line->setAttribute('running_balance', $running); return $line;
            });
        }

        $ledgerSummary = ['debit' => round($lines->sum('debit'), 2), 'credit' => round($lines->sum('credit'), 2), 'closing' => $lines->last()?->running_balance ?? (float) $opening];
        return Inertia::render('Accounting/Reports/Index', compact('accounts','account','lines','opening','from','to','ledgerSummary') + ['report'=>'ledger']);
    }

    public function trial(Request $request)
    {
        $from = $request->date_from ?: today()->startOfMonth()->format('Y-m-d');
        $to = $request->date_to ?: today()->format('Y-m-d');
        $accounts = Account::withSum(['lines as debit' => fn ($q) => $q->whereHas('journalEntry', fn ($q) => $q->posted()->whereBetween('date', [$from, $to]))], 'debit')->withSum(['lines as credit' => fn ($q) => $q->whereHas('journalEntry', fn ($q) => $q->posted()->whereBetween('date', [$from, $to]))], 'credit')->orderBy('code')->get();
        $trialSummary = ['debit' => round($accounts->sum('debit'), 2), 'credit' => round($accounts->sum('credit'), 2)];
        $trialSummary['difference'] = round($trialSummary['debit'] - $trialSummary['credit'], 2);

        return Inertia::render('Accounting/Reports/Index', compact('accounts','from','to','trialSummary') + ['report'=>'trial']);
    }

    public function balanceSheet(Request $request)
    {
        $to = $request->date_to ?: today()->format('Y-m-d');
        $accounts = $this->accountsWithBalances(null, $to);
        $sections = [
            ['key' => 'assets', 'label' => 'Activos', 'accounts' => $accounts->whereIn('type', ['asset_current', 'asset_non_current'])->values()],
            ['key' => 'liabilities', 'label' => 'Pasivos', 'accounts' => $accounts->whereIn('type', ['liability_current', 'liability_long_term'])->values()],
            ['key' => 'equity', 'label' => 'Patrimonio', 'accounts' => $accounts->where('type', 'equity')->values()],
        ];
        $income = $accounts->whereIn('type', ['revenue', 'other_income'])->sum('balance');
        $expenses = $accounts->whereIn('type', ['expense', 'other_expense'])->sum('balance');
        $currentResult = round($income - $expenses, 2);
        $totals = [
            'assets' => round($sections[0]['accounts']->sum('balance'), 2),
            'liabilities' => round($sections[1]['accounts']->sum('balance'), 2),
            'equity' => round($sections[2]['accounts']->sum('balance'), 2),
            'current_result' => $currentResult,
        ];
        $totals['liabilities_equity'] = round($totals['liabilities'] + $totals['equity'] + $currentResult, 2);
        $totals['income'] = round($income, 2); $totals['expenses'] = round($expenses, 2);
        $totals['net_margin'] = $income != 0 ? round($currentResult / $income * 100, 2) : 0;
        $analytics = [
            ['label' => 'Activos', 'value' => $totals['assets'], 'color' => '#1677e8'],
            ['label' => 'Pasivos', 'value' => $totals['liabilities'], 'color' => '#f0a51a'],
            ['label' => 'Patrimonio + resultado', 'value' => round($totals['equity'] + $currentResult, 2), 'color' => '#0c9b81'],
        ];

        return Inertia::render('Accounting/Reports/Index', compact('sections', 'totals', 'analytics', 'to') + ['report' => 'balance-sheet']);
    }

    public function incomeStatement(Request $request)
    {
        $from = $request->date_from ?: today()->startOfYear()->format('Y-m-d');
        $to = $request->date_to ?: today()->format('Y-m-d');
        $accounts = $this->accountsWithBalances($from, $to);
        $sections = [
            ['key' => 'income', 'label' => 'Ingresos', 'accounts' => $accounts->whereIn('type', ['revenue', 'other_income'])->values()],
            ['key' => 'expenses', 'label' => 'Gastos', 'accounts' => $accounts->whereIn('type', ['expense', 'other_expense'])->values()],
        ];
        $totals = ['income' => round($sections[0]['accounts']->sum('balance'), 2), 'expenses' => round($sections[1]['accounts']->sum('balance'), 2)];
        $totals['result'] = round($totals['income'] - $totals['expenses'], 2);
        $totals['margin'] = $totals['income'] != 0 ? round($totals['result'] / $totals['income'] * 100, 2) : 0;
        $trend = $this->incomeTrend($from, $to);

        return Inertia::render('Accounting/Reports/Index', compact('sections', 'totals', 'trend', 'from', 'to') + ['report' => 'income-statement']);
    }

    private function incomeTrend(string $from, string $to)
    {
        $rows = JournalEntryLine::with(['account:id,type,nature', 'journalEntry:id,date,status'])->whereHas('account', fn ($query) => $query->whereIn('type', ['revenue','other_income','expense','other_expense']))->whereHas('journalEntry', fn ($query) => $query->posted()->whereBetween('date', [$from, $to]))->get()->groupBy(fn ($line) => $line->journalEntry->date->format('Y-m'));
        return collect(CarbonPeriod::create(Carbon::parse($from)->startOfMonth(), '1 month', Carbon::parse($to)->startOfMonth()))->map(function ($month) use ($rows) {
            $lines = $rows->get($month->format('Y-m'), collect());
            $income = $lines->whereIn('account.type', ['revenue','other_income'])->sum(fn ($line) => (float) $line->credit - (float) $line->debit);
            $expenses = $lines->whereIn('account.type', ['expense','other_expense'])->sum(fn ($line) => (float) $line->debit - (float) $line->credit);
            return ['label' => $month->translatedFormat('M Y'), 'income' => round($income, 2), 'expenses' => round($expenses, 2), 'result' => round($income - $expenses, 2)];
        })->values();
    }

    private function accountsWithBalances(?string $from, string $to)
    {
        $constraint = function ($query) use ($from, $to) {
            $query->whereHas('journalEntry', function ($entries) use ($from, $to) {
                $entries->posted()->whereDate('date', '<=', $to);
                if ($from) $entries->whereDate('date', '>=', $from);
            });
        };

        return Account::active()->withSum(['lines as debit_total' => $constraint], 'debit')->withSum(['lines as credit_total' => $constraint], 'credit')->orderBy('code')->get()->map(function (Account $account) {
            $debit = (float) ($account->debit_total ?? 0);
            $credit = (float) ($account->credit_total ?? 0);
            $account->setAttribute('balance', $account->nature === 'debit' ? round($debit - $credit, 2) : round($credit - $debit, 2));
            return $account;
        })->filter(fn (Account $account) => abs($account->balance) >= 0.005)->values();
    }

    private function balance(Account $account, $query): string
    {
        $debit = (string) $query->sum('debit');
        $credit = (string) (clone $query)->sum('credit');

        return $account->nature === 'debit' ? bcsub($debit,$credit,2) : bcsub($credit,$debit,2);
    }
}
