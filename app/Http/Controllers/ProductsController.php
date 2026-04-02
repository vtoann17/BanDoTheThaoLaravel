<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()->with(['subcategory', 'brand']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->subcategory_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $sortBy = in_array($request->sort_by, [
            'id',
            'name',
            'price',
            'created_at'
        ]) ? $request->sort_by : 'id';

        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [4, 10, 20, 50])
            ? (int) $request->per_page
            : 10;

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
    // public function create()
    // {
    //     return view('products.create');
    // }
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id' => 'required|exists:brands,id',
            'slug' => 'required|unique:products',
            'description' => 'nullable',
            'price' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'required|in:0,1'
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }

        return Product::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        return Product::with(['subcategory.category', 'brand'])->findOrFail($id);
    }
    public function detail($slug)
{
    $product = Product::with([
        'subcategory.category',
        'brand',
        'variants.values.attribute'
    ])
        ->where('slug', $slug)
        ->where('status', 1)
        ->first();

    if (!$product) {
        return response()->json(['message' => 'Not found'], 404);
    }
    $attributes = [];
    foreach ($product->variants as $variant) {
        foreach ($variant->values as $value) {
            $attr = $value->attribute;

            if (!$attr) continue;

            if (!isset($attributes[$attr->id])) {
                $attributes[$attr->id] = [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'values' => []
                ];
            }
            $attributes[$attr->id]['values'][] = [
                'id' => $value->id,
                'value' => $value->value
            ];
        }
    }
    foreach ($attributes as &$attr) {
        $attr['values'] = collect($attr['values'])
            ->unique('id')
            ->values();
    }

    return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        'price' => $product->price,
        'image' => $product->image,
        'brand' => $product->brand,
        'subcategory' => $product->subcategory,
        'variants' => $product->variants->map(function ($v) {
            return [
                'id' => $v->id,
                'price' => $v->price,
                'stock' => $v->stock,
                'sku' => $v->sku,
                'img' => $v->img,
                'attr_values' => $v->values->pluck('id')
            ];
        }),
        'attributes' => array_values($attributes)
    ]);
}
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|max:255',
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id' => 'required|exists:brands,id',
            'slug' => 'required|unique:products,slug,' . $id,
            'description' => 'nullable',
            'price' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'required|in:0,1'
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }

        $product->update($data);

        return response()->json([
            'message' => 'Cập nhật sản phẩm thành công!',
            'data' => $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Product::destroy($id);
        return response()->json(['message' => 'Deleted']);
    }
}