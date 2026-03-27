<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttributeValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            AttributeValue::with('attribute')->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'attribute_id' => 'required|exists:attributes,id',
            'value' => 'required|string|max:255'
        ]);

        $attributeValue = AttributeValue::create($data);

        return response()->json($attributeValue, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AttributeValue $attributeValue)
    {
        return response()->json(
            $attributeValue->load('attribute')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttributeValue $attributeValue)
    {
        $data = $request->validate([
            'attribute_id' => 'required|exists:attributes,id',
            'value' => 'required|string|max:255'
        ]);

        $attributeValue->update($data);

        return response()->json($attributeValue);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        AttributeValue::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
