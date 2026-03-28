<?php

namespace App\Http\Controllers;

use App\Models\Coupons;
use Illuminate\Http\Request;

class CouponsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Coupons::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0'
        ]);
        $coupons = Coupons::create($data);
        return response()->json([
            'message' => 'Thêm thành công',
            'data' => $coupons
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $coupons = Coupons::findOrfail($id);
        return response()->json($coupons);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $coupons = Coupons::findOrFail($id);
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0'
        ]);
        $coupons->update($data);
        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $coupons
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $coupons = Coupons::findOrFail($id);
        $coupons->delete();

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
