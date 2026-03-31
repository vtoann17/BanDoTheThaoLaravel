<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoriesController extends Controller
{
    public function index(Request $request)
{
    $query = Category::query();

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) { 
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('slug', 'like', "%{$search}%");
        });
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
            'name' => 'required',
            'slug' => 'required|unique:categories',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }
        $category = Category::create($data);

        return response()->json([
            'message' => 'Thêm thành công',
            'data' => $category
        ]);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:categories,slug,' . $id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }
        $category->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        Category::destroy($id);

        return response()->json([
            'message' => 'Xoá thành công'
        ]);
    }
}