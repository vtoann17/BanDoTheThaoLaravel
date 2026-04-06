<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $addresses = Address::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json($addresses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'province_id' => 'required|integer',
            'province_name' => 'required|string',
            'district_id' => 'required|integer',
            'district_name' => 'required|string',
            'ward_code' => 'required|string',
            'ward_name' => 'required|string',
            'address_detail' => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',
            'phone' => 'required|regex:/^[0-9]{9,11}$/',
            'is_default' => 'nullable|boolean',
        ]);

        $data['user_id'] = auth()->id();
        if (!empty($data['is_default'])) {
            Address::where('user_id', auth()->id())
                ->update(['is_default' => false]);
        }

        $address = Address::create($data);

        return response()->json([
            'message' => 'Thêm địa chỉ thành công',
            'data' => $address
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        return response()->json($address);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        $data = $request->validate([
            'province_id' => 'required|integer',
            'province_name' => 'required|string',
            'district_id' => 'required|integer',
            'district_name' => 'required|string',
            'ward_code' => 'required|string',
            'ward_name' => 'required|string',
            'address_detail' => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',
            'phone' => 'required|regex:/^[0-9]{9,11}$/',
            'is_default' => 'nullable|boolean',
        ]);
        if (!empty($data['is_default'])) {
            Address::where('user_id', auth()->id())
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $address
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address)
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        $address->delete();

        return response()->json([
            'message' => 'Xóa địa chỉ thành công'
        ]);
    }
}
