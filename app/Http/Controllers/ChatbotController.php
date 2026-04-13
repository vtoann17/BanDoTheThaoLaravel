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
        $keywords = collect(preg_split('/\s+/', trim($search)))
            ->filter(fn($w) => mb_strlen($w) >= 2)
            ->unique()
            ->values();

        $products = Product::select('id', 'name', 'slug', 'price', 'image', 'subcategory_id', 'brand_id')
            ->with(['brand:id,name', 'subcategory:id,name,category_id'])
            ->where('status', 1)
            ->where(function ($q) use ($search, $keywords) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");

                foreach ($keywords as $kw) {
                    $q->orWhere('name', 'like', "%{$kw}%")
                      ->orWhere('slug', 'like', "%{$kw}%");
                }
            })
            ->limit(6)
            ->get();

        $brands        = Brands::select('id', 'name', 'slug')->get();
        $categories    = Category::select('id', 'name', 'slug')->get();
        $subcategories = Subcategory::select('id', 'name', 'slug', 'category_id')->get();

        return compact('products', 'brands', 'categories', 'subcategories');
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