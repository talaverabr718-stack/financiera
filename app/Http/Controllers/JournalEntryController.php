<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalEntryRequest;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalEntryController extends Controller
{
    public function __construct(private AccountingService $accounting) {}

    public function index(Request $request)
    {
        $entries = JournalEntry::with('user')->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('number', 'like', "%$v%")->orWhere('concept', 'like', "%$v%")))->latest('date')->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('Accounting/Entries/Index', ['entries'=>$entries,'filters'=>$request->only('search','status'),'endpoints'=>['index'=>route('accounting.entries.index'),'create'=>route('accounting.entries.create')]]);
    }

    public function create()
    {
        return Inertia::render('Accounting/Entries/Create', ['accounts' => Account::active()->postable()->orderBy('code')->get(), 'costCenters' => CostCenter::active()->orderBy('code')->get(), 'documentTypes' => ['invoice'=>'Factura','receipt'=>'Recibo','credit_note'=>'Nota de crédito','debit_note'=>'Nota de débito','withholding'=>'Constancia de retención','bank_document'=>'Documento bancario','internal_support'=>'Soporte interno','other'=>'Otro'], 'endpoints'=>['index'=>route('accounting.entries.index'),'store'=>route('accounting.entries.store')]]);
    }

    public function store(JournalEntryRequest $request)
    {
        $entry = $this->accounting->create($request->validated(), auth()->id());

        return redirect()->route('accounting.entries.show', $entry)->with('success', 'Asiento creado en borrador.');
    }

    public function show(JournalEntry $entry)
    {
        $entry->load(['lines.account', 'lines.costCenter', 'user', 'postedBy', 'accountingPeriod', 'reversal', 'reversalOf', 'auditEvents.actor']);

        return Inertia::render('Accounting/Entries/Show', ['entry'=>$entry,'endpoints'=>['index'=>route('accounting.entries.index'),'post'=>route('accounting.entries.post',$entry),'reverse'=>route('accounting.entries.reverse',$entry)]]);
    }

    public function post(JournalEntry $entry)
    {
        $this->accounting->post($entry, auth()->id());

        return back()->with('success', 'Asiento contabilizado.');
    }

    public function reverse(Request $request, JournalEntry $entry)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $reversal = $this->accounting->reverse($entry, $data['reason'], auth()->id());

        return redirect()->route('accounting.entries.show',$reversal)->with('success','Reversión contable registrada.');
    }
}
