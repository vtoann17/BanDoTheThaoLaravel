<?php

namespace App\Http\Controllers;

use App\Models\Brands;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Brands::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:brands',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }
        $brands = Brands::create($data);

        return response()->json([
            'message' => 'Thêm thành công',
            'data' => $brands
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $brands = Brands::findOrFail($id);
        return response()->json($brands);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $brands = Brands::findOrFail($id);
        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:brands,slug,' . $id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }
        $brands->update($data);
        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $brands
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $brands = Brands::findOrFail($id);
        $brands->delete();

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
