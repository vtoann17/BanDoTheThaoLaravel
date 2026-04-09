<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'products_index_' . md5(json_encode($request->query()));

        $result = Cache::remember($cacheKey, 300, function () use ($request) {
            $query = Product::select('id', 'name', 'slug', 'price', 'image', 'status', 'subcategory_id', 'brand_id')
                ->with(['subcategory:id,name,slug', 'brand:id,name,slug,image']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }

            if ($request->filled('subcategory_id')) $query->where('subcategory_id', $request->subcategory_id);
            if ($request->filled('brand_id'))       $query->where('brand_id', $request->brand_id);
            if ($request->filled('status'))         $query->where('status', $request->status);
            if ($request->filled('min_price'))      $query->where('price', '>=', $request->min_price);
            if ($request->filled('max_price'))      $query->where('price', '<=', $request->max_price);

            $sortBy  = in_array($request->sort_by, ['id', 'name', 'price', 'created_at']) ? $request->sort_by : 'id';
            $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
            $perPage = in_array((int) $request->per_page, [4, 10, 20, 50]) ? (int) $request->per_page : 10;

            $paginated = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

            return [
                'data'         => $paginated->items(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|max:255',
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id'       => 'required|exists:brands,id',
            'slug'           => 'required|unique:products',
            'description'    => 'nullable',
            'price'          => 'required|numeric',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status'         => 'required|in:0,1'
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Thêm sản phẩm thành công!',
            'data'    => $product
        ], 201);
    }

    public function show($id)
    {
        $product = Cache::remember("product_id_{$id}", 300, function () use ($id) {
            return Product::select('id', 'name', 'slug', 'price', 'image', 'status', 'description', 'subcategory_id', 'brand_id')
                ->with([
                    'subcategory:id,name,slug,category_id',
                    'subcategory.category:id,name,slug',
                    'brand:id,name,slug,image'
                ])
                ->findOrFail($id);
        });

        return response()->json($product);
    }

    public function detail($slug)
    {
        $product = Cache::remember("product_slug_{$slug}", 300, function () use ($slug) {
            $product = Product::select('id', 'name', 'slug', 'price', 'image', 'description', 'subcategory_id', 'brand_id')
                ->with([
                    'subcategory:id,name,slug,category_id',
                    'subcategory.category:id,name,slug',
                    'brand:id,name,slug,image',
                    'variants:id,product_id,price,stock,sku,img',
                    'variants.values',
                    'variants.values.attribute:id,name',
                ])
                ->where('slug', $slug)
                ->where('status', 1)
                ->first();

            if (!$product) return null;

            $attributes = $product->variants
                ->flatMap(fn($v) => $v->values)
                ->filter(fn($val) => $val->attribute)
                ->groupBy(fn($val) => $val->attribute->id)
                ->map(function ($values) {
                    $attr = $values->first()->attribute;
                    return [
                        'id'     => $attr->id,
                        'name'   => $attr->name,
                        'values' => $values->map(fn($v) => ['id' => $v->id, 'value' => $v->value])
                                          ->unique('id')->values()
                    ];
                })
                ->values();

            return [
                'id'          => $product->id,
                'name'        => $product->name,
                'price'       => $product->price,
                'image'       => $product->image,
                'description' => $product->description,
                'brand'       => $product->brand,
                'subcategory' => $product->subcategory,
                'variants'    => $product->variants->map(fn($v) => [
                    'id'          => $v->id,
                    'price'       => $v->price,
                    'stock'       => $v->stock,
                    'sku'         => $v->sku,
                    'img'         => $v->img,
                    'attr_values' => $v->values->pluck('id')
                ]),
                'attributes'  => $attributes
            ];
        });

        if (!$product) return response()->json(['message' => 'Not found'], 404);

        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name'           => 'required|max:255',
            'subcategory_id' => 'required|exists:subcategories,id',
            'brand_id'       => 'required|exists:brands,id',
            'slug'           => 'required|unique:products,slug,' . $id,
            'description'    => 'nullable',
            'price'          => 'required|numeric',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status'         => 'required|in:0,1'
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('uploads', 'public');
        }

        $product->update($data);

        return response()->json([
            'message' => 'Cập nhật sản phẩm thành công!',
            'data'    => $product
        ]);
    }

    public function destroy($id)
    {
        Product::destroy($id);

        return response()->json(['message' => 'Xóa sản phẩm thành công!']);
    }
}