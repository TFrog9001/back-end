<?php

namespace App\Http\Controllers;

use App\Models\FieldPrice;
use Illuminate\Http\Request;
use App\Models\Field;
use Illuminate\Validation\ValidationException;


class FieldPriceController extends Controller
{
    /**
     * Tạo một mức giá mới cho sân bóng.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'field_id' => 'required|exists:fields,id',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                // 'day_type' => 'required|in:Ngày thường,Cuối tuần,Ngày lễ',
                'price' => 'required|numeric|min:0',
            ]);

            $price = FieldPrice::create($request->all());
            $field = Field::with('prices')->findOrFail($request->field_id);
            return response()->json($field, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
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
    public function delete($id)
    {
        $price = FieldPrice::find($id);

        if (!$price) {
            return response()->json(['message' => 'Field Price not found'], 404);
        }

        $price->delete();
        return response()->json(['message' => 'Field Price deleted successfully']);
    }
}
