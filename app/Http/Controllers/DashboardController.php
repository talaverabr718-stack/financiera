<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CollectionRecord;
use App\Models\CollectionRoute;
use App\Models\CreditApplication;
use App\Models\Loan;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (! Schema::hasTable('loans')) {
            return view('dashboard', [
                'stats' => ['activePortfolio'=>0, 'placed'=>0, 'collectedToday'=>0, 'activeLoans'=>0, 'delinquentLoans'=>0, 'pendingApplications'=>0, 'clients'=>0, 'routesToday'=>0, 'delinquencyRate'=>0],
                'recentApplications' => collect(),
                'recentCollections' => collect(),
            ]);
        }
        $loans = Loan::query();
        $stats = [
            'activePortfolio' => (clone $loans)->whereIn('status', ['active', 'delinquent'])->selectRaw('COALESCE(SUM(principal_balance + interest_balance + fee_balance),0) total')->value('total'),
            'placed' => (clone $loans)->whereBetween('disbursed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('principal'),
            'collectedToday' => CollectionRecord::where('outcome', 'collected')->whereDate('recorded_at', today())->sum('amount'),
            'activeLoans' => (clone $loans)->whereIn('status', ['active', 'delinquent'])->count(),
            'delinquentLoans' => (clone $loans)->where('status', 'delinquent')->count(),
            'pendingApplications' => CreditApplication::whereIn('status', ['submitted', 'review', 'approved'])->count(),
            'clients' => Client::where('status', 'active')->count(),
            'routesToday' => CollectionRoute::whereDate('scheduled_date', today())->count(),
        ];
        $stats['delinquencyRate'] = $stats['activeLoans'] ? round($stats['delinquentLoans'] / $stats['activeLoans'] * 100, 1) : 0;
        $recentApplications = CreditApplication::with(['client', 'product'])->latest()->take(5)->get();
        $recentCollections = CollectionRecord::with(['client', 'collector.user'])->latest('recorded_at')->take(6)->get();

        return view('dashboard', compact('stats', 'recentApplications', 'recentCollections'));
    }
}
