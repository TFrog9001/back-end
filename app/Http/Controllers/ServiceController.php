<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    /**
     * Lấy danh sách tất cả dịch vụ.
     */
    public function serviceWithStaff()
    {
        $services = Service::with('role')->get()->map(function ($service) {
            // Lấy các user có role_id trùng với role_id trong Service
            $service->staffs = User::where('role_id', $service->role_id)->get();
            return $service;
        })->filter(function ($service) {
            return $service->staffs->isNotEmpty();
        })->values();

        return response()->json($services);
    }

    public function index()
    {
        $services = Service::with('role')->get();
        return response()->json($services);
    }



    /**
     * Tạo một dịch vụ mới.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'service' => 'required|string|max:50',
                'description' => 'sometimes|string',
                'fee' => 'required|numeric|min:0',
                'role_id' => 'required'
            ]);

            $service = Service::create($validatedData);

            return response()->json($service, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hiển thị thông tin một dịch vụ cụ thể.
     */
    public function show($id)
    {
        try {
            $service = Service::findOrFail($id);
            return response()->json($service);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Service not found'], 404);
        }
    }

    /**
     * Cập nhật thông tin một dịch vụ cụ thể.
     */
    public function update(Request $request, $id)
    {
        try {
            $service = Service::findOrFail($id);

            $validatedData = $request->validate([
                'service' => 'sometimes|required|string|max:50',
                'description' => 'sometimes|string',
                'fee' => 'sometimes|required|numeric|min:0',
                'role_id' => 'sometimes|required',
            ]);

            $service->update($validatedData);

            return response()->json($service);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Service not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa một dịch vụ cụ thể.
     */
    public function destroy($id)
    {
        try {
            $service = Service::findOrFail($id);
            $service->delete();

            return response()->json(['message' => 'Service deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Service not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
