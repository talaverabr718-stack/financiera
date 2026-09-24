<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\Loan;
use App\Models\SellerProfile;
use App\Services\PermissionService;
use App\Services\PortfolioAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalSearchController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private PortfolioAccessService $portfolioAccess,
    ) {}

    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $results = collect();
        if (mb_strlen($term) >= 2) {
            if ($this->permissions->allows($request->user(), 'clients')) $results = $results->concat($this->portfolioAccess->scopeClients(Client::query(), $request->user())->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")->orWhere('identity_number', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))->take(8)->get()->map(fn ($item) => ['type' => 'Cliente', 'title' => $item->full_name, 'meta' => $item->code, 'url' => route('clients.show', $item)]));
            if ($this->permissions->allows($request->user(), 'loans')) $results = $results->concat($this->portfolioAccess->scopeByClient(Loan::query(), $request->user())->with('client')->where('number', 'like', "%{$term}%")->take(8)->get()->map(fn ($item) => ['type' => 'Crédito', 'title' => $item->number, 'meta' => $item->client->full_name, 'url' => route('loans.show', $item)]));
            if ($this->permissions->allows($request->user(), 'applications')) $results = $results->concat($this->portfolioAccess->scopeByClient(CreditApplication::query(), $request->user())->with('client')->where('number', 'like', "%{$term}%")->take(8)->get()->map(fn ($item) => ['type' => 'Solicitud', 'title' => $item->number, 'meta' => $item->client->full_name, 'url' => route('applications.show', $item)]));
            if ($this->permissions->allows($request->user(), 'collaborators')) $results = $results->concat($this->portfolioAccess->scopeSellers(SellerProfile::query(), $request->user())->with('user')->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")))->take(8)->get()->map(fn ($item) => ['type' => 'Colaborador', 'title' => $item->display_name, 'meta' => $item->code, 'url' => route('collaborators.show', $item)]));
        }

        return Inertia::render('Search/Index', compact('term', 'results'));
    }
}
