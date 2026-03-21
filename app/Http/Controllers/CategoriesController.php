<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoriesController extends Controller
{
    public function index()
    {
        return Category::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:categories'
        ]);

        return Category::create($data);
    }

    public function show($id)
    {
        return Category::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:categories,slug,' . $id
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        Category::destroy($id);

        return response()->json(['message' => 'Deleted']);
    }
}
