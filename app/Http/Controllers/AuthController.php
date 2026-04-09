<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user,
            'token' => $token
        ]);
    }
    public function googleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::where('email', $googleUser->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'avatar' => $googleUser->avatar,
                'provider' => 'google',
                'password' => bcrypt(Str::random(16)),
                'role' => 'user',
            ]);
        } else {
            $user->update([
                'google_id' => $googleUser->id,
                'avatar' => $googleUser->avatar,
                'provider' => 'google',
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return redirect("http://localhost:5173/auth/callback?token=$token");
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Tài khoản không tồn tại'
            ], 404);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Sai mật khẩu'
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token
        ]);
    }
    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }

    public function user(Request $request)
    {
        $userId = $request->user()->id;

        $user = Cache::tags(['users'])->remember("user_{$userId}", 300, function () use ($request) {
            return $request->user();
        });

        return response()->json($user);
    }
}
