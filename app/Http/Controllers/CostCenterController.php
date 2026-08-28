<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CostCenterController extends Controller
{
    public function index()
    {
        return Inertia::render('Accounting/CostCenters/Index', ['centers' => CostCenter::withCount('lines')->orderBy('code')->get(), 'endpoints' => ['store' => route('accounting.cost-centers.store'), 'update' => route('accounting.cost-centers.update', '__CENTER__')]]);
    }

    public function store(Request $request)
    {
        CostCenter::create($this->validated($request));
        return back()->with('success', 'Centro de costo creado.');
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        $costCenter->update($this->validated($request, $costCenter));
        return back()->with('success', 'Centro de costo actualizado sin alterar su historial.');
    }

    private function validated(Request $request, ?CostCenter $center = null): array
    {
        return $request->validate(['code' => ['required','string','max:30',Rule::unique('cost_centers')->ignore($center)], 'name' => ['required','string','max:150'], 'description' => ['nullable','string','max:500'], 'is_active' => ['required','boolean']]);
    }
}
