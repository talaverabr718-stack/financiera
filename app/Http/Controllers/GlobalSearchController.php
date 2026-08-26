<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\Loan;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $results = collect();
        if (mb_strlen($term) >= 2) {
            $results = $results
                ->concat(Client::where(fn($q)=>$q->where('full_name','like',"%{$term}%")->orWhere('identity_number','like',"%{$term}%")->orWhere('code','like',"%{$term}%"))->take(8)->get()->map(fn($item)=>['type'=>'Cliente','title'=>$item->full_name,'meta'=>$item->code,'url'=>route('clients.show',$item)]))
                ->concat(Loan::with('client')->where('number','like',"%{$term}%")->take(8)->get()->map(fn($item)=>['type'=>'Crédito','title'=>$item->number,'meta'=>$item->client->full_name,'url'=>route('loans.show',$item)]))
                ->concat(CreditApplication::with('client')->where('number','like',"%{$term}%")->take(8)->get()->map(fn($item)=>['type'=>'Solicitud','title'=>$item->number,'meta'=>$item->client->full_name,'url'=>route('applications.show',$item)]))
                ->concat(SellerProfile::with('user')->whereHas('user',fn($q)=>$q->where('name','like',"%{$term}%"))->take(8)->get()->map(fn($item)=>['type'=>'Colaborador','title'=>$item->user->name,'meta'=>$item->code,'url'=>route('collaborators.show',$item)]));
        }

        return view('search.index', compact('term', 'results'));
    }
}
