<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;


class ForgotPasswordController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        $otp = rand(100000, 999999);

        $user->update([
            'otp' => $otp,
            'otp_expired_at' => now()->addMinutes(5)
        ]);

        Mail::raw("Mã OTP của bạn là: $otp", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Mã OTP đặt lại mật khẩu');
        });

        return response()->json([
            'message' => 'Đã gửi OTP về email'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email không tồn tại'
            ], 400);
        }

        if ((string)$user->otp !== (string)$request->otp) {
            return response()->json([
                'message' => 'OTP không đúng'
            ], 400);
        }

        if (now()->gt($user->otp_expired_at)) {
            return response()->json([
                'message' => 'OTP đã hết hạn'
            ], 400);
        }

        return response()->json([
            'message' => 'OTP hợp lệ'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        $user = User::where('email', $request->email)->first();

        $user->update([
            'password' => Hash::make($request->password),
            'otp' => null,
            'otp_expired_at' => null
        ]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công'
        ]);
    }
}
