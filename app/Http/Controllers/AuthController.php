<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // ✅ 1️⃣ تسجيل الدخول
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        // البحث عن المستخدم بالاسم
        $user = User::where('email', $credentials['email'])->first();

        // التحقق من صحة البيانات
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
        }

        // 🔥 منع تسجيل الدخول إذا كان الحساب معطلاً
        if ($user->is_active == 0) {
            return response()->json(['message' => 'الحساب معطل. يرجى التواصل مع الإدارة.'], 403);
        }

        // إنشاء التوكن باستخدام JWTAuth
        $token = JWTAuth::fromUser($user);

        // تحديد واجهة المستخدم بناءً على نوع المستخدم
        $redirect_to = $this->getUserRedirect($user->user_type);

        return $this->respondWithToken($token, $user->user_type, $redirect_to);

    }

    // ✅ 2️⃣ دالة إرجاع التوكن مع معلوماته
    protected function respondWithToken($token, $userType, $redirectTo)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user_type' => $userType,
            'redirect_to' => $redirectTo
        ]);
    }

    // ✅ 3️⃣ تحديد واجهة المستخدم بناءً على نوعه
    protected function getUserRedirect($userType)
    {
        $routes = [
            'healthMinistry' => '/admin.home',
            'doctor' => '/doctorhome',
            'hospital' => '/dashboard/hospital',
            'patient' => '/dashboard/patient'
        ];

        return $routes[$userType] ?? '/dashboard';
    }

    // ✅ 4️⃣ تجديد التوكن (Refresh Token)
    public function refreshToken()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
    }

    // ✅ 5️⃣ تسجيل خروج المستخدم
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken()); // إبطال التوكن
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }
}
