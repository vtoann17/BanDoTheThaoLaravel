<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;


class UserController extends Controller
{
    /**
     * GET /api/users
     */
    public function index(Request $request)
    {
        $query = User::query();
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $sortBy  = in_array($request->input('sort_by'), ['id', 'name', 'email', 'created_at'])
            ? $request->input('sort_by', 'id')
            : 'id';
        $sortDir = in_array($request->input('sort_dir'), ['asc', 'desc'])
            ? $request->input('sort_dir', 'desc')
            : 'desc';

        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) $request->input('per_page', 10);
        $users   = $query->paginate($perPage);
        return response()->json([
            'data'      => $users->items(),
            'total'     => $users->total(),
            'last_page' => $users->lastPage(),
            'page'      => $users->currentPage(),
            'per_page'  => $users->perPage(),
        ]);
    }

    /**
     * GET /api/users/{id}
     * Chi tiết 1 người dùng
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }
        return response()->json(['data' => $user]);
    }
    /**
     * POST /api/users
     * Thêm người dùng mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:user,staff,admin',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ], [
            'name.required'     => 'Tên người dùng không được để trống',
            'email.required'    => 'Email không được để trống',
            'email.email'       => 'Email không hợp lệ',
            'email.unique'      => 'Email đã tồn tại trong hệ thống',
            'password.required' => 'Mật khẩu không được để trống',
            'password.min'      => 'Mật khẩu phải ít nhất 6 ký tự',
            'role.required'     => 'Vui lòng chọn vai trò',
            'role.in'           => 'Vai trò không hợp lệ',
            'avatar.image'      => 'File phải là ảnh',
            'avatar.max'        => 'Ảnh không được vượt quá 2MB',
        ]);
        $data = [
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ];
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        $user = User::create($data);
        return response()->json([
            'message' => 'Thêm người dùng thành công',
            'data'    => $user,
        ], 201);
    }
    /**
     * PUT /api/users/{id}  
     * Cập nhật người dùng
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password' => 'nullable|string|min:6',
            'role'     => 'required|in:user,staff,admin',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ], [
            'name.required'  => 'Tên người dùng không được để trống',
            'email.required' => 'Email không được để trống',
            'email.email'    => 'Email không hợp lệ',
            'email.unique'   => 'Email đã được sử dụng bởi tài khoản khác',
            'password.min'   => 'Mật khẩu phải ít nhất 6 ký tự',
            'role.required'  => 'Vui lòng chọn vai trò',
            'role.in'        => 'Vai trò không hợp lệ',
            'avatar.image'   => 'File phải là ảnh',
            'avatar.max'     => 'Ảnh không được vượt quá 2MB',
        ]);
        $user->name  = $request->name;
        $user->email = $request->email;
        $user->role  = $request->role;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }
        $user->save();
        return response()->json([
            'message' => 'Cập nhật người dùng thành công',
            'data'    => $user,
        ]);
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'new_password.required'     => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min'          => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'new_password.confirmed'    => 'Xác nhận mật khẩu mới không khớp.',
        ]);
        $user = auth()->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không chính xác.'
            ], 422);
        }
        $user->password = Hash::make($request->new_password);
        $user->save();
        return response()->json([
            'message' => 'Đổi mật khẩu thành công!'
        ]);
    }
    /**
     * DELETE /api/users/{id}
     * Xóa người dùng
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->delete();
        return response()->json(['message' => 'Xóa người dùng thành công']);
    }
}
