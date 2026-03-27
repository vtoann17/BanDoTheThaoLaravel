<?php

namespace App\Http\Controllers;

use App\Models\Variant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VariantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         return Variant::with(['product', 'values'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|unique:variants,sku',
            'img' => 'nullable|string',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'sale' => 'nullable|integer',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'attribute_values' => 'required|array'
        ]);

        $variant = Variant::create($data);

        $variant->values()->sync($data['attribute_values']);

        return response()->json($variant->load('values'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Variant $variant)
    {
        return $variant->load(['product', 'values']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Variant $variant)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|unique:variants,sku,' . $variant->id,
            'img' => 'nullable|string',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'sale' => 'nullable|integer',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'attribute_values' => 'required|array'
        ]);

        $variant->update($data);

        $variant->values()->sync($data['attribute_values']);

        return response()->json($variant->load('values'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Variant::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
