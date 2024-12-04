<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use Illuminate\Support\Facades\Storage;


class UserController extends Controller
{
    public function register(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string',  // Không kiểm tra uniqueness nữa vì sẽ kiểm tra sau
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Kiểm tra nếu validation thất bại
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Kiểm tra nếu số điện thoại đã tồn tại trong cơ sở dữ liệu
        $user = User::where('phone', $request->phone)->first();

        if ($user) {
            // Nếu người dùng đã tồn tại, cập nhật thông tin của họ
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password); // Cập nhật mật khẩu
            $user->save(); // Lưu lại thông tin đã cập nhật

            return response()->json([
                'message' => 'User information updated successfully!',
            ], 200);
        } else {
            // Nếu không tồn tại, tạo mới người dùng
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role_id' => 3,
            ]);

            return response()->json([
                'message' => 'User registered successfully!',
            ], 201);
        }
    }

    public function index()
    {
        $users = User::all();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function show($id)
    {
        $users = User::findOrFail($id);

        return response()->json([
            'users' => $users,
        ]);
    }

    public function getCustomers()
    {
        $users = User::where('role_id', '=', '3')->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function getStaffs()
    {
        $users = User::with('role')->where('role_id', '!=', '3')->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function addUser(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|regex:/^\d{10}$/',
            'password' => 'required|string|min:8|confirmed',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // Giới hạn kích thước 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle avatar upload
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role_id' => $request->role ? $request->role : 3,
            'avatar' => $avatarPath,
        ]);

        return response()->json([
            'message' => 'User registered successfully!',
            'user' => $user, // Trả về thông tin user (nếu cần)
        ], 201);
    }


    public function editUser(Request $request, $id)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|regex:/^\d{10}$/',
            'password' => 'nullable|string|min:8|confirmed',
            'avatar' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find user
        $user = User::findOrFail($id);

        // Handle avatar upload if present
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Save new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        // Update password only if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Update other fields
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->role_id = $request->role ?? $request->role_id;
        $user->save();

        return response()->json([
            'user' => $user,
            'message' => 'User updated successfully!',
        ], 200);
    }


    public function delete($id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json([
            'message' => 'User deleted success!'
        ]);
    }

    /// Staff
    public function addStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            // 'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role_id' => $request->role ? $request->role : 3,
        ]);

        return response()->json([
            'message' => 'User registered successfully!',
        ], 201);
    }

    public function editStaff(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            // 'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $user = User::find($id);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'phone' => $request->phone,
            'user' => $user,
            'message' => 'User updated successfully!',
        ], 201);
    }

    public function deleteStaff($id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json([
            'message' => 'User deleted success!'
        ]);
    }
}
