<?php

namespace App\Http\Controllers;

use App\Models\Favourites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavouritesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $favourites = Favourites::with('product')
            ->where('user_id', $user->id)
            ->get();
        return response()->json($favourites);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $user = Auth::user();
        $exists = Favourites::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Sản phẩm đã có trong yêu thích'
            ], 400);
        }
        $favourite = Favourites::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
        ]);
        return response()->json([
            'message' => 'Đã thêm vào yêu thích',
            'data' => $favourite
        ]);
    }
    /**
     * Display the specified resource.
     */
    public function show(Favourites $favourites)
    {
        return response()->json($favourites->load('product'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Favourites $favourites)
    {
        return response()->json([
            'message' => 'Không hỗ trợ update'
        ], 400);
    }

    /**
     * Remove the specified resource from storage.
     */

    public function destroy($productId)
    {
        $user = Auth::user();
        $favourite = Favourites::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();
        if (!$favourite) {
            return response()->json([
                'message' => 'Không tìm thấy trong yêu thích'
            ], 404);
        }
        $favourite->delete();
        return response()->json([
            'message' => 'Đã xóa khỏi yêu thích'
        ]);
    }
}
