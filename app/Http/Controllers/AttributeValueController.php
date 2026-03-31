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
    public function index(Request $request)
    {
        $query = AttributeValue::with('attribute');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                  ->orWhereHas('attribute', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('attribute_id')) {
            $query->where('attribute_id', $request->attribute_id);
        }

        $sortBy  = in_array($request->sort_by, ['id', 'value', 'attribute_id', 'created_at']) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [4, 10, 20, 50]) ? (int) $request->per_page : 10;

        $result = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'data'         => $result->items(),
            'total'        => $result->total(),
            'per_page'     => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page'    => $result->lastPage(),
        ]);
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
