<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Brands;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array|max:20',
        ]);

        $userMsg = $request->message;

        try {
            // 1. Xây dựng ngữ cảnh dữ liệu (Tìm kiếm + Gợi ý)
            $context = $this->buildContext($userMsg);
            
            // 2. Xây dựng danh sách tin nhắn gửi cho AI
            $messages = $this->buildMessages($context, $request->history ?? [], $userMsg);

            // 3. Gọi API Groq
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.groq.key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.3-70b-versatile',
                'messages'    => $messages,
                'temperature' => 0.3,
                'max_tokens'  => 1000,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'AI Service Error'], 500);
            }

            $text = data_get($response->json(), 'choices.0.message.content', 'Xin lỗi, tôi gặp chút trục trặc.');

            // 4. Bóc tách dữ liệu sản phẩm JSON từ tag <products>
            $products = [];
            if (preg_match('/<products>(.*?)<\/products>/s', $text, $matches)) {
                $jsonStr = trim($matches[1]);
                try {
                    $decoded = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        // Chỉ giữ lại các ID thực sự tồn tại trong context để tránh AI "bịa" dữ liệu
                        $allowedIds = collect($context['products'])
                            ->merge($context['suggestions'])
                            ->pluck('id')
                            ->toArray();

                        $products = array_values(array_filter($decoded, function ($p) use ($allowedIds) {
                            return isset($p['id']) && in_array($p['id'], $allowedIds);
                        }));
                    }
                } catch (\Exception $e) {
                    Log::error("Chatbot JSON Parse Error: " . $e->getMessage());
                }
                // Xóa tag JSON khỏi nội dung tin nhắn để hiển thị cho người dùng
                $text = trim(str_replace($matches[0], '', $text));
            }

            return response()->json([
                'reply'    => $text,
                'products' => $products,
            ]);

        } catch (\Exception $e) {
            Log::error("Chatbot General Error: " . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    private function buildContext(string $search): array
    {
        // Phân tích giá (Hỗ trợ: dưới 500k, trên 1tr, khoảng 200k-500k)
        $maxPrice = null;
        $minPrice = null;

        // Regex tìm kiếm khoảng giá hoặc giá trần/sàn
        if (preg_match('/(duoi|duới|thap hon|<|re hon)\s*(\d+(?:\.\d+)?)\s*(k|ngan|trieu|tr)/iu', $search, $matches)) {
            $value = (float)$matches[2];
            $unit = strtolower($matches[3]);
            $maxPrice = ($unit === 'k' || $unit === 'ngan') ? $value * 1000 : $value * 1000000;
        }
        
        if (preg_match('/(tren|trên|cao hon|>)\s*(\d+(?:\.\d+)?)\s*(k|ngan|trieu|tr)/iu', $search, $matches)) {
            $value = (float)$matches[2];
            $unit = strtolower($matches[3]);
            $minPrice = ($unit === 'k' || $unit === 'ngan') ? $value * 1000 : $value * 1000000;
        }

        $keywords = collect(preg_split('/\s+/', trim($search)))
            ->filter(fn($w) => mb_strlen($w) >= 2)
            ->unique();

        // Query tìm sản phẩm chính
        $query = Product::select('id', 'name', 'slug', 'price', 'image', 'brand_id')
            ->with(['brand:id,name'])
            ->where('status', 1);

        $query->where(function ($q) use ($search, $keywords, $minPrice, $maxPrice) {
            $q->where('name', 'like', "%{$search}%");
            foreach ($keywords as $kw) {
                $q->orWhere('name', 'like', "%{$kw}%");
            }
            if ($minPrice) $q->where('price', '>=', $minPrice);
            if ($maxPrice) $q->where('price', '<=', $maxPrice);
        });

        $products = $query->limit(6)->get();

        // Gợi ý thêm (AI Suggestions): Lấy sản phẩm ngẫu nhiên nếu kết quả tìm kiếm ít
        $suggestions = collect();
        if ($products->count() < 3) {
            $suggestions = Product::select('id', 'name', 'slug', 'price', 'image', 'brand_id')
                ->with(['brand:id,name'])
                ->where('status', 1)
                ->whereNotIn('id', $products->pluck('id'))
                ->inRandomOrder()
                ->limit(4)
                ->get();
        }

        return compact('products', 'suggestions');
    }

    private function buildMessages(array $ctx, array $history, string $userMsg): array
    {
        $mainProducts = $ctx['products'];
        $suggested = $ctx['suggestions'];

        $format = fn($p) => "- ID:{$p->id} | Tên:{$p->name} | Giá:" . number_format($p->price) . "đ | Slug:{$p->slug} | Ảnh:{$p->image}";

        $mainList = $mainProducts->count() > 0 ? $mainProducts->map($format)->join("\n") : "KHÔNG CÓ";
        $suggestList = $suggested->count() > 0 ? $suggested->map($format)->join("\n") : "KHÔNG CÓ";

        $systemPrompt = <<<SYS
Bạn là chuyên viên tư vấn tại SportShop. Trả lời thân thiện, ưu tiên tư vấn sản phẩm khách tìm.

=== DANH SÁCH SẢN PHẨM KHỚP YÊU CẦU ===
{$mainList}

=== DANH SÁCH SẢN PHẨM GỢI Ý THÊM (NẾU CẦN) ===
{$suggestList}

=== QUY TẮC ===
1. Nếu có sản phẩm khớp, hãy tư vấn trực tiếp.
2. Nếu không có sản phẩm khớp, hãy xin lỗi và dùng danh sách "GỢI Ý THÊM" để chào mời khách.
3. Khi giới thiệu sản phẩm, BẮT BUỘC chèn JSON vào cuối phản hồi trong tag: <products>[{"id":...}]</products>.
4. Chỉ dùng dữ liệu ID và Tên chính xác từ danh sách được cung cấp.
SYS;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (collect($history)->take(-6) as $m) {
            $messages[] = ['role' => $m['role'], 'content' => $m['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMsg];

        return $messages;
    }
}