<?php

namespace App\Http\Controllers;

use App\Models\Reviews;
use App\Models\Order;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Reviews::with(['user:id,name,email', 'product:id,name,image']);

        // Tìm kiếm theo comment hoặc tên sản phẩm
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        // Lọc theo số sao
        if ($rating = $request->input('rating')) {
            $query->where('rating', (int) $rating);
        }

        // Lọc theo product_id (dùng cho trang chi tiết sản phẩm)
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', (int) $productId);
        }

        // Sắp xếp
        $allowedSorts = ['id', 'rating', 'created_at'];
        $sortBy  = in_array($request->input('sort_by'), $allowedSorts)
            ? $request->input('sort_by')
            : 'id';
        $sortDir = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Phân trang
        $perPage = min((int) $request->input('per_page', 10), 100);
        $result  = $query->paginate($perPage);

        return response()->json([
            'data'         => $result->items(),
            'total'        => $result->total(),
            'current_page' => $result->currentPage(),
            'last_page'    => $result->lastPage(),
            'per_page'     => $result->perPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Điều kiện để được đánh giá (phù hợp với DB hiện tại - không có order_id):
     *   1. User phải đăng nhập
     *   2. User phải có ít nhất 1 đơn hàng "completed" chứa sản phẩm đó
     *   3. Chưa đánh giá sản phẩm này (1 user - 1 product - 1 review)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:1000',
        ]);

        // Kiểm tra user có đơn hàng "completed" chứa sản phẩm này không
        // order_details -> variant_id -> variants.product_id
        $hasBought = Order::where('user_id', $user->id)
            ->where('order_status', 'completed')
            ->whereHas('items', function ($q) use ($data) {
                $q->whereHas('variant', function ($vq) use ($data) {
                    $vq->where('product_id', $data['product_id']);
                });
            })
            ->exists();

        if (!$hasBought) {
            return response()->json([
                'message' => 'Bạn chỉ có thể đánh giá sản phẩm sau khi đơn hàng hoàn thành',
            ], 403);
        }

        // Chặn đánh giá trùng: 1 user chỉ đánh giá 1 lần mỗi sản phẩm
        $alreadyReviewed = Reviews::where('user_id', $user->id)
            ->where('product_id', $data['product_id'])
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'message' => 'Bạn đã đánh giá sản phẩm này rồi',
            ], 400);
        }

        $review = Reviews::create([
            'user_id'    => $user->id,
            'product_id' => $data['product_id'],
            'rating'     => $data['rating'],
            'comment'    => $data['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Đánh giá thành công',
            'data'    => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $review = Reviews::with(['user:id,name', 'product:id,name'])->findOrFail($id);
        return response()->json($review);
    }

    /**
     * Update the specified resource in storage.
     * Chỉ chủ sở hữu hoặc admin mới được sửa.
     */
    public function update(Request $request, $id)
    {
        $user   = $request->user();
        $review = Reviews::findOrFail($id);

        if ($user->role !== 'admin' && $review->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        $data = $request->validate([
            'rating'  => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data'    => $review,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user   = $request->user();
        $review = Reviews::findOrFail($id);

        if ($user->role !== 'admin' && $review->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Xóa thành công']);
    }
}