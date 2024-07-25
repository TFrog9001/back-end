<?php

namespace App\Http\Controllers;

use App\Models\Field;
use App\Models\FieldPrice;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class FieldController extends Controller
{
    /**
     * Lấy danh sách tất cả các sân bóng.
     */
    public function index()
    {
        $fields = Field::with('prices')->get(); // Include prices for each field
        return response()->json($fields);
    }

    /**
     * Tạo một sân bóng mới cùng với các khung giờ giá tiền.
     */
    public function store(Request $request)
    {
        try {
            // Bước 1: Xác thực dữ liệu đầu vào
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'type' => 'required|in:11,7,5',
                'status' => 'required|in:Hoạt động,Đang sửa chữa,Không hoạt động',
                'prices' => 'required|array',
                'prices.*.start_time' => 'required|date_format:H:i',
                'prices.*.end_time' => 'required|date_format:H:i|after:prices.*.start_time',
                // 'prices.*.day_type' => 'required|in:Ngày thường,Cuối tuần,Ngày lễ',
                'prices.*.price' => 'required|numeric|min:0',
            ]);

            // Bước 2: Bắt đầu transaction
            \DB::beginTransaction();

            // Bước 3: Tạo sân bóng mới
            $field = Field::create($validatedData);

            // Bước 4: Tạo các khung giờ giá tiền cho sân bóng
            foreach ($validatedData['prices'] as $priceData) {
                $priceData['field_id'] = $field->id;
                FieldPrice::create($priceData);
            }

            // Bước 5: Hoàn tất transaction
            \DB::commit();

            // Bước 6: Trả về phản hồi JSON với thông tin sân bóng và khung giờ giá tiền
            $field->load('prices'); // Load prices to include them in the response
            return response()->json($field, 201);
        } catch (ValidationException $e) {
            // Xử lý ngoại lệ liên quan đến lỗi xác thực
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            // Xử lý ngoại lệ không tìm thấy model
            \DB::rollBack();
            return response()->json(['error' => 'Model not found'], 404);
        } catch (QueryException $e) {
            // Xử lý ngoại lệ liên quan đến cơ sở dữ liệu
            \DB::rollBack();
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            // Xử lý ngoại lệ chung
            \DB::rollBack();
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hiển thị thông tin một sân bóng cụ thể.
     */
    public function show($id)
    {
        try {
            $field = Field::with('prices')->findOrFail($id); // Include prices for each field
            return response()->json($field);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Field not found'], 404);
        }
    }

    /**
     * Cập nhật thông tin một sân bóng cụ thể.
     */
    public function update(Request $request, $id)
    {
        try {
            $field = Field::findOrFail($id);

            $request->validate([
                'name' => 'string|max:255',
                'location' => 'string|max:255',
                'type' => 'in:11,7,5',
                'status' => 'in:Hoạt động,Đang sửa chữa,Không hoạt động',
            ]);

            $field->update($request->only(['name', 'location', 'type', 'status']));
            return response()->json($field);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Field not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa một sân bóng cụ thể.
     */
    public function destroy($id)
    {
        try {
            $field = Field::findOrFail($id);
            $field->delete();
            return response()->json(['message' => 'Field deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Field not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
