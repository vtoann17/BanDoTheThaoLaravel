<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoriesController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
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