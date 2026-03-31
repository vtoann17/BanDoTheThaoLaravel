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
    public function index(Request $request)
    {
        $query = Attribute::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $sortBy = in_array($request->sort_by, ['id', 'name', 'created_at']) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [4, 10, 20, 50]) ? (int) $request->per_page : 10;

        $result = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'data' => $result->items(),
            'total' => $result->total(),
            'per_page' => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page' => $result->lastPage(),
        ]);
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
