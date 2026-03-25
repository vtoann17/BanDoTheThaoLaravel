<?php

namespace App\Http\Controllers;

use App\Models\Reviews;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Reviews::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        $reviews = Reviews::create($data);

        return response()->json([
            'message' => 'Thêm thành công',
            'data' => $reviews
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $reviews = Reviews::findOrFail($id);
        return response()->json($reviews);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $reviews = Reviews::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'product_id' => 'sometimes|exists:products,id',
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        $reviews->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $reviews
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $reviews = Reviews::findOrFail($id);
        $reviews->delete();

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
