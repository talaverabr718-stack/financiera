<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelDelinquencyCaseRequest;
use App\Http\Requests\RecalculateLoanDelinquencyRequest;
use App\Models\DelinquencyCase;
use App\Models\Loan;
use App\Services\DelinquencyTrackingService;
use App\Support\OperationalMesa;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DelinquencyCaseController extends Controller
{
    public function __construct(private DelinquencyTrackingService $delinquency) {}

    public function index(Request $request)
    {
        $sort = $request->string('sort')->toString() ?: 'days';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'days' => 'current_days',
            'balance' => 'overdue_balance',
            'started_on' => 'started_on',
            'client' => 'clients.full_name',
            'code' => 'code',
        ];
        $column = $sortColumns[$sort] ?? 'current_days';
        if (in_array($sort, ['started_on', 'client', 'code'], true) && ! $request->filled('direction')) {
            $direction = 'asc';
        }

        $cases = DelinquencyCase::query()
            ->with(['client.activeAssignment.seller.user', 'loan', 'oldestInstallment', 'items'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(function ($query) use ($term) {
                    $query->where('code', 'like', $term)
                        ->orWhereHas('client', fn ($client) => $client->where('full_name', 'like', $term)->orWhere('identity_number', 'like', $term))
                        ->orWhereHas('loan', fn ($loan) => $loan->where('number', 'like', $term));
                });
            })
            ->when($request->input('status', 'active') !== 'all', fn ($query) => $query->where('status', $request->input('status', 'active')));

        $cases = match ($sort) {
            'client' => $cases->orderBy(
                \App\Models\Client::select('full_name')->whereColumn('clients.id', 'delinquency_cases.client_id'),
                $direction
            ),
            default => $cases->orderBy($column, $direction),
        };

        $cases = $cases->orderBy('id')->paginate(15)->withQueryString();
        $base = DelinquencyCase::query();
        $active = (clone $base)->where('status', DelinquencyCase::STATUS_ACTIVE)->count();
        $resolved = (clone $base)->where('status', DelinquencyCase::STATUS_RESOLVED)->count();
        $cancelled = (clone $base)->where('status', DelinquencyCase::STATUS_CANCELLED)->count();
        $total = $active + $resolved + $cancelled;
        $aging = [
            ['label' => '1-7d', 'min' => 1, 'max' => 7],
            ['label' => '8-30d', 'min' => 8, 'max' => 30],
            ['label' => '31-60d', 'min' => 31, 'max' => 60],
            ['label' => '60d+', 'min' => 61, 'max' => null],
        ];
        $buckets = [];
        foreach ($aging as $band) {
            $count = (clone $base)->where('status', DelinquencyCase::STATUS_ACTIVE)
                ->where('current_days', '>=', $band['min'])
                ->when($band['max'], fn ($query) => $query->where('current_days', '<=', $band['max']))
                ->count();
            $buckets[] = ['label' => $band['label'], 'added' => $count];
        }
        $situation = $total === 0
            ? 'No hay expedientes de mora. Recalcula para armar el listado con cuotas vencidas e impagas.'
            : "{$total} expediente".($total === 1 ? '' : 's')." · {$active} activo".($active === 1 ? '' : 's')." · {$resolved} resuelto".($resolved === 1 ? '' : 's');

        return Inertia::render('Delinquency/Index', [
            'cases' => $cases,
            'board' => [
                'briefing' => [
                    'title' => 'Clientes en mora',
                    'date_label' => OperationalMesa::dateLabel(),
                    'situation' => $situation,
                ],
                'stats' => ['total' => $total],
                'mix' => [
                    ['key' => 'active', 'tone' => 'bad', 'label' => 'En mora', 'value' => $active],
                    ['key' => 'resolved', 'tone' => 'ok', 'label' => 'Resueltos', 'value' => $resolved],
                    ['key' => 'cancelled', 'tone' => 'muted', 'label' => 'Cancelados', 'value' => $cancelled],
                ],
                'growth' => array_merge(OperationalMesa::runningPoints($buckets), [
                    'delta' => $active,
                    'added' => $active,
                ]),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'active'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'endpoints' => [
                'index' => route('delinquency.index'),
                'recalculate' => route('delinquency.recalculate'),
            ],
        ]);
    }

    public function recalculateAll(Request $request)
    {
        $result = $this->delinquency->recalculateDueLoans(now(), [
            'trigger' => 'manual',
            'actor_id' => $request->user()->id,
        ]);

        return back()->with('success', "Mora recalculada. Créditos procesados: {$result['processed']}.");
    }

    public function recalculate(RecalculateLoanDelinquencyRequest $request, Loan $loan)
    {
        $this->delinquency->recalculateLoan($loan, now(), [
            'trigger' => 'manual',
            'actor_id' => $request->user()->id,
            'daily_rate' => $request->validated('daily_rate'),
        ]);

        return back()->with('success', 'La mora del crédito se recalculó y el monto por día de retraso quedó aplicado en cada cuota vencida.');
    }

    public function cancel(CancelDelinquencyCaseRequest $request, DelinquencyCase $delinquencyCase)
    {
        $this->delinquency->cancel($delinquencyCase, $request->validated('reason'), $request->user()->id);

        return back()->with('success', 'El expediente de mora fue cancelado y permanece en el historial.');
    }

    public function reopen(CancelDelinquencyCaseRequest $request, DelinquencyCase $delinquencyCase)
    {
        $this->delinquency->reopen($delinquencyCase, $request->validated('reason'), $request->user()->id);

        return back()->with('success', 'El expediente de mora fue reactivado.');
    }
}
