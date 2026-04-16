<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // Thêm thư viện này để check unique

class AttributeValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AttributeValue::with('attribute');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                    ->orWhereHas('attribute', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('attribute_id')) {
            $query->where('attribute_id', $request->attribute_id);
        }

        $sortBy = in_array($request->sort_by, ['id', 'value', 'attribute_id', 'created_at']) ? $request->sort_by : 'id';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [5, 10, 20, 50, 999]) ? (int) $request->per_page : 5;

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
        $request->validate([
            'attribute_id' => 'required|exists:attributes,id',
            'values' => 'required|array|min:1',
            'values.*' => [
                'required',
                'string',
                'max:255',
                // Check không được trùng value trong cùng 1 attribute_id
                Rule::unique('attribute_values', 'value')->where(function ($query) use ($request) {
                    return $query->where('attribute_id', $request->attribute_id);
                })
            ]
        ], [
            'values.*.unique' => 'Một trong các giá trị bạn nhập đã tồn tại trong thuộc tính này.'
        ]);

        $createdValues = [];
        foreach ($request->values as $val) {
            $createdValues[] = AttributeValue::create([
                'attribute_id' => $request->attribute_id,
                'value' => trim($val)
            ]);
        }

        return response()->json([
            'message' => 'Thêm các giá trị thành công',
            'data' => $createdValues
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AttributeValue $attributeValue)
    {
        return response()->json(
            $attributeValue->load('attribute')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'values' => 'required|array|min:1',
            'values.*.id' => 'nullable|integer',
            'values.*.value' => 'required|string|max:255'
        ]);

        foreach ($data['values'] as $item) {
            $query = AttributeValue::where('attribute_id', $id)
                        ->where('value', trim($item['value']));
            
            if (!empty($item['id'])) {
                $query->where('id', '!=', $item['id']);
            }
            if ($query->exists()) {
                return response()->json([
                    'message' => 'Giá trị "' . $item['value'] . '" đã tồn tại. Vui lòng không nhập trùng!'
                ], 422);
            }
        }

        $existingIds = AttributeValue::where('attribute_id', $id)->pluck('id')->toArray();
        $incomingIds = array_filter(array_column($data['values'], 'id'));
        $idsToDelete = array_diff($existingIds, $incomingIds);
        
        AttributeValue::destroy($idsToDelete);
        
        foreach ($data['values'] as $val) {
            if (!empty($val['id'])) {
                AttributeValue::where('id', $val['id'])->update(['value' => trim($val['value'])]);
            } else {
                AttributeValue::create([
                    'attribute_id' => $id,
                    'value' => trim($val['value'])
                ]);
            }
        }

        return response()->json(['message' => 'Cập nhật danh sách giá trị thành công']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        AttributeValue::destroy($id);

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }
}