<?php

namespace App\Http\Controllers;

use App\Models\Shipping;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Address;

class ShippingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function provinces()
    {
        $res = Http::withHeaders([
            'Token' => env('GHN_TOKEN')
        ])->get('https://online-gateway.ghn.vn/shiip/public-api/master-data/province');

        return response()->json($res->json());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function districts($province_id)
    {
        $res = Http::withHeaders([
            'Token' => env('GHN_TOKEN')
        ])->get('https://online-gateway.ghn.vn/shiip/public-api/master-data/district', [
            'province_id' => $province_id
        ]);

        return response()->json($res->json());
    }

    /**
     * Display the specified resource.
     */
    public function wards($district_id)
    {
        $res = Http::withHeaders([
            'Token' => env('GHN_TOKEN')
        ])->get('https://online-gateway.ghn.vn/shiip/public-api/master-data/ward', [
            'district_id' => $district_id
        ]);

        return response()->json($res->json());
    }


    /**
     * Update the specified resource in storage.
     */
    public function calculateFee(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id'
        ]);

        $address = Address::findOrFail($request->address_id);

        $res = Http::withHeaders([
            'Token' => env('GHN_TOKEN'),
            'ShopId' => env('GHN_SHOP_ID'),
        ])->post('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee', [
            "service_type_id" => 2,
            "from_district_id" => (int) env('GHN_FROM_DISTRICT'),
            "to_district_id" => (int) $address->district_id,
            "to_ward_code" => $address->ward_code,
            "height" => 10,
            "length" => 20,
            "weight" => 500,
            "width" => 20,
            "insurance_value" => 100000,
        ]);

        return response()->json($res->json());
    }
}
