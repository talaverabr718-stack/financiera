<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditProductRequest;
use App\Models\CreditProduct;
use App\Services\DocumentSequenceService;

class CreditProductController extends Controller
{
    public function index()
    {
        return view('products.index', ['products' => CreditProduct::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('products.form', ['product' => new CreditProduct]);
    }

    public function edit(CreditProduct $product)
    {
        return view('products.form', compact('product'));
    }

    public function store(CreditProductRequest $request, DocumentSequenceService $sequences)
    {
        $product = CreditProduct::create($this->payload($request, $sequences));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Producto financiero creado.', 'product' => $product], 201);
        }

        return redirect()->route('products.index')->with('success', 'Producto financiero creado.');
    }

    public function update(CreditProductRequest $request, CreditProduct $product)
    {
        $product->update($this->payload($request));

        return redirect()->route('products.index')->with('success', 'Configuración del producto actualizada.');
    }

    private function payload(CreditProductRequest $request, ?DocumentSequenceService $sequences = null): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->isQuickCreate() ? true : $request->boolean('is_active');

        if ($request->isQuickCreate()) {
            $template = CreditProduct::query()->where('is_active', true)->orderBy('id')->first();
            $data['code'] = $sequences?->next('credit_product', 'PRD-');
            $data['allowed_frequencies'] = $template?->allowed_frequencies ?? ['weekly', 'biweekly', 'monthly'];
            $data['allowed_interest_methods'] = $template?->allowed_interest_methods ?? ['flat', 'declining_balance', 'french'];
            $data['payment_allocation_order'] = $template?->payment_allocation_order ?? ['delinquency', 'fees', 'interest', 'principal'];
            $data['minimum_term'] = $template?->minimum_term ?? 4;
            $data['maximum_term'] = $template?->maximum_term ?? 60;
            $data['default_administrative_fee'] = $template?->default_administrative_fee ?? '0.00';
            $data['default_interest_method'] = $template?->default_interest_method;
            $data['delinquency_method'] = $template?->delinquency_method;
            $data['delinquency_rate'] = $template?->delinquency_rate;
        }

        return $data;
    }
}
