<?php

namespace App\Http\Controllers;

use App\Models\Variant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VariantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Variant::with(['product', 'values']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $sortBy = in_array($request->sort_by, ['id', 'sku', 'price', 'stock', 'created_at']) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [4, 10, 20, 50]) ? (int) $request->per_page : 10;

        $result = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'data' => $result->items(),
            'total' => $result->total(),
            'per_page' => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page' => $result->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|unique:variants,sku',
            'img' => 'nullable|image|max:5120',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'sale' => 'nullable|integer',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'attribute_values' => 'required|array'
        ]);
        if ($request->hasFile('img')) {
            $data['img'] = $request->file('img')->store('variants', 'public');
        }
        $variant = Variant::create($data);

        $variant->values()->sync($data['attribute_values']);

        return response()->json($variant->load('values'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Variant $variant)
    {
        return $variant->load(['product', 'values']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Variant $variant)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|unique:variants,sku,' . $variant->id,
            'img' => 'nullable|image|max:5120',
            'stock' => 'required|integer',
            'price' => 'required|numeric',
            'sale' => 'nullable|integer',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'attribute_values' => 'required|array'
        ]);
        if ($request->hasFile('img')) {
            if ($variant->img) {
                \Storage::disk('public')->delete($variant->img);
            }
            $data['img'] = $request->file('img')->store('variants', 'public');
        } else {
            unset($data['img']);
        }
        $variant->update($data);

        $variant->values()->sync($data['attribute_values']);

        return response()->json($variant->load('values'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Variant::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}
