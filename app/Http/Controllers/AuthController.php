<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // تحقق من صحة المدخلات
        $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
        ]);

        // البحث عن المستخدم بناءً على الاسم
        $user = User::where('name', $request->name)->first();

        // تحقق من وجود المستخدم
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // تحقق من كلمة المرور
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // تحقق من حالة الحساب
        if (!$user->is_active) {
            return response()->json(['error' => 'Account is inactive'], 403);
        }

        // إنشاء التوكن
        $token = JWTAuth::fromUser($user);

        // التوجيه حسب نوع المستخدم
        $redirectUrl = match ($user->user_type) {
            'Admin' => '/admin',
            'Doctor' => '/doctorhome',
            'Hospital' => '/hospital/dashboard',
            'Patient' => '/patient/dashboard',
            default => null
        };

        if (!$redirectUrl) {
            return response()->json(['error' => 'Invalid user type'], 400);
        }

        // إرجاع استجابة تسجيل الدخول
        return response()->json([
            'message' => 'Login successful',
            'redirect_url' => $redirectUrl,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60, // مدة انتهاء التوكن بالدقائق
            'user' => $user,
        ], 200);
    }

    // دالة Refresh Token
    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token not provided'], 400);
        }
    }

    // دالة تسجيل الخروج
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Successfully logged out']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout'], 500);
        }
    }
}
