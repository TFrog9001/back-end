<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class EquipmentController extends Controller
{
    /**
     * Lấy danh sách tất cả thiết bị.
     */
    public function index()
    {
        $equipment = Equipment::all();
        return response()->json($equipment);
    }

    /**
     * Tạo một thiết bị mới.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                
            ]);

            $equipment = Equipment::create($validatedData);

            return response()->json($equipment, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hiển thị thông tin một thiết bị cụ thể.
     */
    public function show($serial_number)
    {
        try {
            $equipment = Equipment::where('serial_number',$serial_number)->get();
            return response()->json($equipment);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Equipment not found'], 404);
        }
    }

    /**
     * Cập nhật thông tin một thiết bị cụ thể.
     */
    public function update(Request $request, $id)
    {
        try {
            $equipment = Equipment::findOrFail($id);

            $validatedData = $request->validate([
                'name' => 'string|max:255',
                'state' => 'string|in:available,in_use,damaged',
            ]);

            $equipment->update($validatedData);

            return response()->json($equipment);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Equipment not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa một thiết bị cụ thể.
     */
    public function destroy($id)
    {
        try {
            $equipment = Equipment::findOrFail($id);
            $equipment->delete();

            return response()->json(['message' => 'Equipment deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Equipment not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Gán thiết bị cho sân.
     */
    public function allocateToField(Request $request, $equipmentId)
    {
        try {
            $equipment = Equipment::findOrFail($equipmentId);
            $validatedData = $request->validate([
                'field_id' => 'required|exists:fields,id',
            ]);

            $field = Field::findOrFail($validatedData['field_id']);
            $equipment->fields()->attach($field);

            return response()->json(['message' => 'Equipment allocated to field successfully']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Equipment or Field not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hủy gán thiết bị khỏi sân.
     */
    public function deallocateFromField(Request $request, $equipmentId)
    {
        try {
            $equipment = Equipment::findOrFail($equipmentId);
            $validatedData = $request->validate([
                'field_id' => 'required|exists:fields,id',
            ]);

            $field = Field::findOrFail($validatedData['field_id']);
            $equipment->fields()->detach($field);

            return response()->json(['message' => 'Equipment deallocated from field successfully']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Equipment or Field not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
