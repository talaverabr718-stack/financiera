<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(private AccountingService $accounting){}
    public function index(Request $request){$entries=JournalEntry::with('user')->when($request->status,fn($q,$v)=>$q->where('status',$v))->when($request->search,fn($q,$v)=>$q->where(fn($q)=>$q->where('number','like',"%$v%")->orWhere('concept','like',"%$v%")))->latest('date')->latest('id')->paginate(20)->withQueryString();return view('accounting.entries.index',compact('entries'));}
    public function create(){return view('accounting.entries.create',['accounts'=>Account::active()->postable()->orderBy('code')->get()]);}
    public function store(JournalEntryRequest $request){$entry=$this->accounting->create($request->validated(),auth()->id()??User::value('id'));return redirect()->route('accounting.entries.show',$entry)->with('success','Asiento creado en borrador.');}
    public function show(JournalEntry $entry){$entry->load(['lines.account','user','reversal','reversalOf']);return view('accounting.entries.show',compact('entry'));}
    public function post(JournalEntry $entry){$this->accounting->post($entry);return back()->with('success','Asiento contabilizado.');}
    public function reverse(Request $request,JournalEntry $entry){$data=$request->validate(['reason'=>['required','string','max:500']]);$reversal=$this->accounting->reverse($entry,$data['reason'],auth()->id()??User::value('id'));return redirect()->route('accounting.entries.show',$reversal)->with('success','Reversión contable registrada.');}
}
