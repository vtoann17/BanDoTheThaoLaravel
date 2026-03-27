<?php

namespace App\Http\Controllers;

use App\Models\VariantValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VariantValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return VariantValue::with(['variant', 'attributeValue'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'variant_id' => 'required|exists:variants,id',
            'attribute_value_id' => 'required|exists:attribute_values,id'
        ]);

        $variantValue = VariantValue::create($data);

        return response()->json($variantValue, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(VariantValue $variantValue)
    {
        return $variantValue->load(['variant', 'attributeValue']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VariantValue $variantValue)
    {
        $data = $request->validate([
            'variant_id' => 'required|exists:variants,id',
            'attribute_value_id' => 'required|exists:attribute_values,id'
        ]);

        $variantValue->update($data);

        return response()->json($variantValue);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        VariantValue::destroy($id);
        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
