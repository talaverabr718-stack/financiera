<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditProductRequest;
use App\Models\CreditProduct;

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

    public function store(CreditProductRequest $request)
    {
        $product = CreditProduct::create($this->validated($request));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Producto financiero creado.', 'product' => $product], 201);
        }

        return redirect()->route('products.index')->with('success', 'Producto financiero creado.');
    }

    public function update(CreditProductRequest $request, CreditProduct $product)
    {
        $product->update($this->validated($request));

        return redirect()->route('products.index')->with('success', 'Configuración del producto actualizada.');
    }

    private function validated(CreditProductRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
