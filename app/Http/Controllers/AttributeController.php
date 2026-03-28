<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Attribute::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name'
        ]);

        $attribute = Attribute::create($data);

        return response()->json($attribute, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute)
    {
        return response()->json($attribute);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attribute $attribute)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id
        ]);

        $attribute->update($data);

        return response()->json($attribute);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Attribute::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
