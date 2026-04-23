<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Brands;
use App\Models\Category;
use App\Models\Subcategory;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array|max:20',
        ]);

        $userMsg  = $request->message;
        $context  = $this->buildContext($userMsg);
        $messages = $this->buildMessages($context, $request->history ?? [], $userMsg);

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer ' . config('services.groq.key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model'       => 'llama-3.3-70b-versatile',
            'messages'    => $messages,
            'temperature' => 0.3,
            'max_tokens'  => 800,
        ]);

        if ($response->failed()) {
            return response()->json([
                'error'   => 'AI Service Error',
                'details' => $response->json(),
            ], 500);
        }

        $text = data_get(
            $response->json(),
            'choices.0.message.content',
            'Xin lỗi, tôi không thể trả lời lúc này.'
        );

        $products = [];

        preg_match('/<products>(.*?)<\/products>/s', $text, $matches);

        if (!empty($matches[1])) {
            $jsonStr = trim($matches[1]);
            try {
                $decoded = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $validIds = $context['products']->pluck('id')->toArray();
                    $products = array_values(array_filter(
                        $decoded,
                        fn($p) => isset($p['id']) && in_array($p['id'], $validIds)
                    ));
                }
            } catch (\Throwable $e) {
                $products = [];
            }

            $text = trim(str_replace($matches[0], '', $text));
        }

        return response()->json([
            'reply'    => $text,
            'products' => $products,
        ]);
    }

    private function buildContext(string $search): array
    {
        // 1. Phân tích khoảng giá từ câu nói của user
        $priceRange = $this->parsePriceInfo($search);

        // 2. Lọc từ khóa: Loại bỏ các từ liên quan đến giá/số và các từ giao tiếp để không làm nhiễu
        $stopWords = [
            'giá', 'dưới', 'trên', 'từ', 'đến', 'khoảng', 'k', 'tr', 'triệu', 'trieu', 'nhỏ', 'lớn', 'hơn', 'chỉ', 'mức', 'tầm',
            'tìm', 'mua', 'sản', 'phẩm', 'cho', 'tôi', 'có', 'nào', 'không', 'những', 'các', 'cái', 'loại', 'kiếm', 'xem', 'hàng', 'đồ'
        ];

        $keywords = collect(preg_split('/\s+/', trim($search)))
            ->filter(fn($w) => mb_strlen($w) >= 2)
            ->filter(fn($w) => !is_numeric(str_replace(['k', 'tr'], '', mb_strtolower($w)))) // Bỏ qua các số hoặc từ như "500", "500k"
            ->filter(fn($w) => !in_array(mb_strtolower($w), $stopWords))
            ->unique()
            ->values();

        $query = Product::select('id', 'name', 'slug', 'price', 'image', 'subcategory_id', 'brand_id')
            ->with(['brand:id,name', 'subcategory:id,name,category_id'])
            ->where('status', 1);

        // 3. Áp dụng điều kiện lọc giá nếu hệ thống phân tích được
        if ($priceRange) {
            $query->whereBetween('price', $priceRange);
        }

        // 4. Áp dụng điều kiện lọc tên nếu người dùng có nhập từ khóa (ngoài giá và stop words)
        if ($keywords->isNotEmpty()) {
            $query->where(function ($q) use ($keywords) {
                // Tạo chuỗi tìm kiếm từ các từ khóa còn lại
                $searchPhrase = $keywords->implode(' ');
                $q->where('name', 'like', "%{$searchPhrase}%")
                  ->orWhere('slug', 'like', "%{$searchPhrase}%");

                foreach ($keywords as $kw) {
                    $q->orWhere('name', 'like', "%{$kw}%")
                      ->orWhere('slug', 'like', "%{$kw}%");
                }
            });
        }

        $products = $query->limit(6)->get();

        $brands        = Brands::select('id', 'name', 'slug')->get();
        $categories    = Category::select('id', 'name', 'slug')->get();
        $subcategories = Subcategory::select('id', 'name', 'slug', 'category_id')->get();

        return compact('products', 'brands', 'categories', 'subcategories');
    }

    /**
     * Hàm trích xuất ý định tìm kiếm theo giá từ tin nhắn người dùng.
     */
    private function parsePriceInfo(string $text): ?array
    {
        $text = mb_strtolower($text);

        // Chuyển đổi các định dạng viết tắt phổ biến ở VN (k, tr, triệu) thành số thực
        // Ví dụ: 500k -> 500000, 2 tr -> 2000000
        $text = preg_replace_callback('/(\d+)\s*(k|tr|triệu|trieu)\b/', function ($matches) {
            $num = (int)$matches[1];
            $unit = $matches[2];
            if ($unit === 'k') return $num * 1000;
            if (in_array($unit, ['tr', 'triệu', 'trieu'])) return $num * 1000000;
            return $num;
        }, $text);

        // Trường hợp 1: Khoảng giá cụ thể (VD: "từ 500000 đến 1000000")
        if (preg_match('/từ\s+(\d+)\s+(đến|den)\s+(\d+)/', $text, $matches)) {
            return [(int)$matches[1], (int)$matches[3]];
        }

        // Trường hợp 2: Dưới mức giá (VD: "dưới 500000", "nhỏ hơn 500000")
        if (preg_match('/(dưới|nhỏ hơn|<)\s+(\d+)/', $text, $matches)) {
            return [0, (int)$matches[2]];
        }

        // Trường hợp 3: Trên mức giá (VD: "trên 1000000", "lớn hơn 1000000")
        if (preg_match('/(trên|lớn hơn|>)\s+(\d+)/', $text, $matches)) {
            return [(int)$matches[2], 999999999]; // Đặt mức trần thật cao
        }

        // Trường hợp 4: Khoảng giá ước lượng (VD: "khoảng 500000") -> lấy dao động +- 20%
        if (preg_match('/khoảng\s+(\d+)/', $text, $matches)) {
            $base = (int)$matches[1];
            return [$base * 0.8, $base * 1.2];
        }

        // Không tìm thấy điều kiện giá
        return null; 
    }

    private function buildMessages(array $ctx, array $history, string $userMsg): array
    {
        $hasProducts = $ctx['products']->count() > 0;

        $productsStr = $hasProducts
            ? $ctx['products']->map(
                fn($p) =>
                "- id:{$p->id} | ten:{$p->name} | gia:{$p->price} | slug:{$p->slug} | image:{$p->image} | brand:{$p->brand?->name} | subcategory:{$p->subcategory?->name}"
            )->join("\n")
            : 'KHONG_CO_SAN_PHAM';

        $productRule = $hasProducts
            ? <<<RULE
Khi goi y san pham, them JSON vao CUOI phan hoi theo dinh dang:
<products>[{"id":1,"name":"Ten san pham","price":500000,"slug":"ten-san-pham","image":"duong-dan-anh","brand":{"name":"Nike"}}]</products>
Chi tra JSON hop le, khong them text trong the <products>.
RULE
            : 'KHONG duoc tra ve the <products> vi khong co san pham phu hop.';

        $systemPrompt = <<<SYS
Ban la tro ly mua sam the thao cua SportShop. Tra loi bang tieng Viet, than thien, ngan gon.

=== QUY TAC BAT BUOC ===
1. Chi duoc goi y san pham CO TRONG danh sach "SAN PHAM TIM DUOC" duoi day.
2. TUYET DOI KHONG duoc tu sang tao, tu them hoac de xuat san pham KHONG co trong danh sach.
3. Neu danh sach la KHONG_CO_SAN_PHAM, hay thong bao lich su rang shop chua co san pham phu hop va goi y user thu tu khoa khac.
4. Khong duoc gia mao ten thuong hieu hay ten san pham.

=== QUY TAC TRA SAN PHAM ===
{$productRule}

=== SAN PHAM TIM DUOC ===
{$productsStr}
SYS;

        $messages   = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        foreach (collect($history)->take(-6) as $m) {
            if (in_array($m['role'] ?? '', ['user', 'assistant']) && !empty($m['content'])) {
                $messages[] = [
                    'role'    => $m['role'],
                    'content' => $m['content'],
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMsg];

        return $messages;
    }
}