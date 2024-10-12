<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

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
                'quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'state' => 'sometimes|string|max:50',
            ]);

            $supply = Supply::create($validatedData);

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
    public function show($id)
    {
        try {
            $supply = Supply::findOrFail($id);
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
            ]);

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
