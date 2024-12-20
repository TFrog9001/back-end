<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class SupplyController extends Controller
{
    /**
     * Lấy danh sách tất cả hàng tiêu dùng.
     */
    public function index()
    {
        $supplies = Supply::all();
        return response()->json($supplies);
    }

    /**
     * Tạo một hàng tiêu dùng mới.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
                'quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'state' => 'sometimes|string|max:50',
            ]);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images/supplies', 'public');
            }

            $supply = Supply::create([
                'name' => $request->name,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'image' => $imagePath,
            ]);

            return response()->json($supply, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hiển thị thông tin một hàng tiêu dùng cụ thể.
     */
    public function show($serial_number)
    {
        try {
            $supply = Supply::where('serial_number', $serial_number)->get();
            return response()->json($supply);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Supply not found'], 404);
        }
    }

    /**
     * Cập nhật thông tin một hàng tiêu dùng cụ thể.
     */
    public function update(Request $request, $id)
    {
        try {
            $supply = Supply::findOrFail($id);

            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'quantity' => 'sometimes|required|integer|min:0',
                'price' => 'sometimes|required|numeric|min:0',
                'state' => 'sometimes|string|max:50',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                if ($supply->image) {
                    Storage::disk('public')->delete($supply->image);
                }
                $validatedData['image'] = $request->file('image')->store('images/supplies', 'public');
            }

            // Loại bỏ các trường null trước khi cập nhật
            $validatedData = array_filter($validatedData, function ($value) {
                return $value !== null;
            });

            // Cập nhật bản ghi
            $supply->update($validatedData);

            return response()->json($supply);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Supply not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }



    /**
     * Xóa một hàng tiêu dùng cụ thể.
     */
    public function destroy($id)
    {
        try {
            $supply = Supply::findOrFail($id);
            $supply->delete();

            return response()->json(['message' => 'Supply deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Supply not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
