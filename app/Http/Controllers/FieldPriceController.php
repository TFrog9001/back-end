<?php

namespace App\Http\Controllers;

use App\Models\FieldPrice;
use Illuminate\Http\Request;
use App\Models\Field;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class FieldPriceController extends Controller
{
    /**
     * Tạo một mức giá mới cho sân bóng.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'field_id' => 'required|exists:fields,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'required|numeric',
        ]);

        $newStart = $validated['start_time'];
        $newEnd = $validated['end_time'];
        $fieldId = $validated['field_id'];

        // Lấy các khung giờ hiện có của sân
        $existingPrices = FieldPrice::where('field_id', $fieldId)
            ->where(function ($query) use ($newStart, $newEnd) {
                $query->whereBetween('start_time', [$newStart, $newEnd])
                    ->orWhereBetween('end_time', [$newStart, $newEnd]);
            })
            ->get();

        // Xử lý cập nhật các khung giờ
        foreach ($existingPrices as $price) {
            if ($price->start_time < $newStart && $price->end_time > $newStart) {
                // Cập nhật khung giờ trước
                $price->end_time = $newStart;
                $price->save();
            } elseif ($price->start_time < $newEnd && $price->end_time > $newEnd) {
                // Cập nhật khung giờ sau
                $price->start_time = $newEnd;
                $price->save();
            } elseif ($price->start_time >= $newStart && $price->end_time <= $newEnd) {
                // Xóa khung giờ nằm hoàn toàn trong khung giờ mới
                $price->delete();
            }
        }

        // Thêm giá mới
        $fieldPrice = FieldPrice::create($validated);
        return response()->json($fieldPrice, 201);
    }

    /**
     * Cập nhật thông tin mức giá cụ thể cho sân bóng.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'required|numeric',
        ]);

        $fieldPrice = FieldPrice::findOrFail($id);
        $newStart = $validated['start_time'];
        $newEnd = $validated['end_time'];

        // Lấy các khung giờ hiện có của sân, ngoại trừ khung giờ đang cập nhật
        $existingPrices = FieldPrice::where('field_id', $fieldPrice->field_id)
            ->where('id', '<>', $id)
            ->where(function ($query) use ($newStart, $newEnd) {
                $query->whereBetween('start_time', [$newStart, $newEnd])
                    ->orWhereBetween('end_time', [$newStart, $newEnd]);
            })
            ->get();

        // Xử lý cập nhật các khung giờ
        foreach ($existingPrices as $price) {
            if ($price->start_time < $newStart && $price->end_time > $newStart) {
                // Cập nhật khung giờ trước
                $price->end_time = $newStart;
                $price->save();
            } elseif ($price->start_time < $newEnd && $price->end_time > $newEnd) {
                // Cập nhật khung giờ sau
                $price->start_time = $newEnd;
                $price->save();
            } elseif ($price->start_time >= $newStart && $price->end_time <= $newEnd) {
                // Xóa khung giờ nằm hoàn toàn trong khung giờ mới
                $price->delete();
            }
        }

        // Cập nhật khung giờ hiện tại
        $fieldPrice->update($validated);
        return response()->json($fieldPrice);
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