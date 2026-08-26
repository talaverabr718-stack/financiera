<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function journal(Request $request){$from=$request->date_from?:today()->startOfMonth()->format('Y-m-d');$to=$request->date_to?:today()->format('Y-m-d');$entries=JournalEntry::posted()->with(['lines.account','user'])->whereBetween('date',[$from,$to])->orderBy('date')->paginate(20)->withQueryString();return view('accounting.reports.journal',compact('entries','from','to'));}
    public function ledger(Request $request){$from=$request->date_from?:today()->startOfMonth()->format('Y-m-d');$to=$request->date_to?:today()->format('Y-m-d');$accounts=Account::active()->orderBy('code')->get();$account=$request->account?Account::find($request->integer('account')):null;$lines=collect();$opening='0.00';if($account){$opening=$this->balance($account,JournalEntryLine::where('account_id',$account->id)->whereHas('journalEntry',fn($q)=>$q->posted()->whereDate('date','<',$from)));$lines=JournalEntryLine::with('journalEntry')->where('account_id',$account->id)->whereHas('journalEntry',fn($q)=>$q->posted()->whereBetween('date',[$from,$to]))->get()->sortBy('journalEntry.date');}return view('accounting.reports.ledger',compact('accounts','account','lines','opening','from','to'));}
    public function trial(Request $request){$from=$request->date_from?:today()->startOfMonth()->format('Y-m-d');$to=$request->date_to?:today()->format('Y-m-d');$accounts=Account::withSum(['lines as debit'=>fn($q)=>$q->whereHas('journalEntry',fn($q)=>$q->posted()->whereBetween('date',[$from,$to]))],'debit')->withSum(['lines as credit'=>fn($q)=>$q->whereHas('journalEntry',fn($q)=>$q->posted()->whereBetween('date',[$from,$to]))],'credit')->orderBy('code')->get();return view('accounting.reports.trial',compact('accounts','from','to'));}
    private function balance(Account $account,$query):string{$debit=(string)$query->sum('debit');$credit=(string)(clone $query)->sum('credit');return $account->nature==='debit'?bcsub($debit,$credit,2):bcsub($credit,$debit,2);}
}
