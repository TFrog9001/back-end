<?php

namespace App\Http\Controllers;

use App\Models\FieldPrice;
use Illuminate\Http\Request;

class FieldPriceController extends Controller
{
    /**
     * Lấy danh sách tất cả các mức giá cho sân bóng.
     */
    public function index()
    {
        $prices = FieldPrice::with('field')->get();
        return response()->json($prices);
    }

    /**
     * Tạo một mức giá mới cho sân bóng.
     */
    public function store(Request $request)
    {
        $request->validate([
            'field_id' => 'required|exists:fields,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            // 'day_type' => 'required|in:Ngày thường,Cuối tuần,Ngày lễ',
            'price' => 'required|numeric|min:0',
        ]);

        $price = FieldPrice::create($request->all());
        return response()->json($price, 201);
    }

    /**
     * Hiển thị thông tin mức giá cụ thể cho sân bóng.
     */
    public function show($id)
    {
        $price = FieldPrice::with('field')->find($id);

        if (!$price) {
            return response()->json(['message' => 'Field Price not found'], 404);
        }

        return response()->json($price);
    }

    /**
     * Cập nhật thông tin mức giá cụ thể cho sân bóng.
     */
    public function update(Request $request, $id)
    {
        $price = FieldPrice::find($id);

        if (!$price) {
            return response()->json(['message' => 'Field Price not found'], 404);
        }

        $request->validate([
            'field_id' => 'exists:fields,id',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i|after:start_time',
            // 'day_type' => 'in:Ngày thường,Cuối tuần,Ngày lễ',
            'price' => 'numeric|min:0',
        ]);

        $price->update($request->all());
        return response()->json($price);
    }

    /**
     * Xóa một mức giá cụ thể cho sân bóng.
     */
    public function destroy($id)
    {
        $price = FieldPrice::find($id);

        if (!$price) {
            return response()->json(['message' => 'Field Price not found'], 404);
        }

        $price->delete();
        return response()->json(['message' => 'Field Price deleted successfully']);
    }
}
