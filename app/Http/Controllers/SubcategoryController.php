<?php

namespace App\Http\Controllers;

use App\Models\Subcategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Subcategory::with('category');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $sortBy = in_array($request->sort_by, [
            'id',
            'name',
            'slug',
            'created_at'
        ]) ? $request->sort_by : 'id';

        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [4, 10, 20, 50])
            ? (int) $request->per_page
            : 10;

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
            'name' => 'required|max:255',
            'slug' => 'required|unique:subcategories',
            'category_id' => 'required|exists:categories,id'
        ]);

        return Subcategory::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        return Subcategory::with('category')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $subcategory = Subcategory::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:subcategories,slug,' . $id,
            'category_id' => 'required|exists:categories,id'
        ]);

        $subcategory->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công!',
            'data' => $subcategory
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Subcategory::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công!'
        ]);
    }

    public function getByCategory($category_id)
    {
        return Subcategory::where('category_id', $category_id)->get();
    }
}
