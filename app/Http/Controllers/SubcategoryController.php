<?php

namespace App\Http\Controllers;

use App\Models\Subcategory;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Subcategory::select('id', 'name', 'slug', 'category_id')
            ->with('category:id,name,slug');

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

        $sortBy  = in_array($request->sort_by, ['id', 'name', 'slug', 'created_at']) ? $request->sort_by : 'id';
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|max:255',
            'slug'        => 'required|unique:subcategories',
            'category_id' => 'required|exists:categories,id'
        ]);

        $subcategory = Subcategory::create($data);

        return response()->json([
            'message' => 'Thêm thành công!',
            'data'    => $subcategory
        ], 201);
    }

    public function show($id)
    {
        $subcategory = Subcategory::select('id', 'name', 'slug', 'category_id')
            ->with('category:id,name,slug')
            ->findOrFail($id);

        return response()->json($subcategory);
    }

    public function update(Request $request, $id)
    {
        $subcategory = Subcategory::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|max:255',
            'slug'        => 'required|unique:subcategories,slug,' . $id,
            'category_id' => 'required|exists:categories,id'
        ]);

        $subcategory->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công!',
            'data'    => $subcategory
        ]);
    }

    public function destroy($id)
    {
        Subcategory::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công!'
        ]);
    }

    public function getByCategory($category_id)
    {
        $subcategories = Subcategory::select('id', 'name', 'slug', 'category_id')
            ->where('category_id', $category_id)
            ->orderBy('name')
            ->get();

        return response()->json($subcategories);
    }
}