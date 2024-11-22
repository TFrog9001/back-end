<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }
    /**
     * Tạo vai trò và gán quyền
     */
    public function createRoleWithPermissions(Request $request)
    {
        // Validate đầu vào
        $request->validate([
            'role_name' => 'required|string|unique:roles,role_name|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Tạo vai trò
        $role = Role::create([
            'role_name' => $request->role_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Gán quyền nếu có
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully with permissions!',
            'role' => $role,
            'permissions' => $role->permissions,
        ]);
    }

    public function updateRole(Request $request, $roleId)
    {
        // Validate input và kiểm tra trùng tên
        $validated = $request->validate([
            'role_name' => [
                'required',
                'string',
                'max:255',
                // Kiểm tra unique, loại trừ vai trò hiện tại bằng cách sử dụng `ignore`
                Rule::unique('roles')->ignore($roleId),
            ],
        ]);

        try {
            // Tìm vai trò theo ID
            $role = Role::findOrFail($roleId);

            // Cập nhật tên vai trò
            $role->role_name = $validated['role_name'];
            $role->save();

            return response()->json([
                'message' => 'Vai trò đã được cập nhật thành công.',
                'role' => $role,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Không tìm thấy vai trò với ID được cung cấp.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật vai trò.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Lấy danh sách vai trò và quyền của nó
     */
    public function getRolesWithPermissions()
    {
        $roles = Role::with('permissions')->get();

        return response()->json($roles);
    }

    /**
     * Gán quyền cho vai trò
     */
    public function assignPermissionsToRole(Request $request, $roleId)
    {
        // Validate đầu vào
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::find($roleId);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Gán quyền
        $role->permissions()->syncWithoutDetaching($request->permissions);

        return response()->json([
            'message' => 'Permissions assigned successfully!',
            'role' => $role,
            'permissions' => $role->permissions,
        ]);
    }

    /**
     * Thu hồi quyền từ vai trò
     */
    public function revokePermissionsFromRole(Request $request, $roleId)
    {
        // Validate đầu vào
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::find($roleId);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Thu hồi quyền
        $role->permissions()->detach($request->permissions);

        return response()->json([
            'message' => 'Permissions revoked successfully!',
            'role' => $role,
            'permissions' => $role->permissions,
        ]);
    }

    /**
     * Xóa vai trò
     */
    public function deleteRole($roleId)
    {
        $role = Role::find($roleId);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Xóa vai trò và quan hệ với quyền
        $role->permissions()->detach(); // Xóa tất cả quyền liên kết
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully!',
        ]);
    }
}
