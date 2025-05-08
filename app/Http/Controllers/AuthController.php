<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // ✅ 1️⃣ تسجيل الدخول
// ✅ 1️⃣ تسجيل الدخول
public function login(Request $request)
{
    // تحقق من البيانات المدخلة
    $credentials = $request->validate([
        'email' => 'required|string',
        'password' => 'required|string'
    ]);

    Log::info('محاولة تسجيل الدخول', ['email' => $credentials['email']]);

    // البحث عن المستخدم بالبريد الإلكتروني
    $user = User::where('email', $credentials['email'])->first();

    // التحقق من صحة البريد الإلكتروني
    if (!$user) {
        Log::error('البريد الإلكتروني غير موجود', ['email' => $credentials['email']]);
        return response()->json(['message' => 'البريد الإلكتروني غير صحيح'], 401);
    }

    // التحقق من صحة كلمة المرور
    if (!Hash::check($credentials['password'], $user->password)) {
        Log::error('كلمة المرور غير صحيحة', ['email' => $credentials['email']]);
        return response()->json(['message' => 'كلمة المرور غير صحيحة'], 401);
    }

    // 🔥 منع تسجيل الدخول إذا كان الحساب معطلاً
    if ($user->is_active == 0) {
        Log::warning('حساب معطل', ['email' => $credentials['email']]);
        return response()->json(['message' => 'الحساب معطل. يرجى التواصل مع الإدارة.'], 403);
    }

    // إنشاء التوكن باستخدام JWTAuth
    try {
        $token = JWTAuth::fromUser($user);
        Log::info('تم إنشاء التوكن بنجاح', ['email' => $credentials['email'], 'token' => $token]);
    } catch (\Exception $e) {
        Log::error('فشل في إنشاء التوكن', ['email' => $credentials['email'], 'error' => $e->getMessage()]);
        return response()->json(['message' => 'فشل في إنشاء التوكن'], 500);
    }

    // تحديد واجهة المستخدم بناءً على نوع المستخدم
    $redirect_to = $this->getUserRedirect($user->user_type);

    Log::info('تسجيل الدخول بنجاح', ['email' => $credentials['email'], 'user_type' => $user->user_type]);

    return $this->respondWithToken($token, $user->user_type, $redirect_to);
}

    // ✅ 2️⃣ الرد مع التوكن
    protected function respondWithToken($token, $userType, $redirectTo)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60, // مدة صلاحية التوكن (بالدقائق)
            'user_type' => $userType,
            'redirect_to' => $redirectTo,
        ]);
    }

    // ✅ 3️⃣ تحديد واجهة المستخدم بناءً على نوعه
    protected function getUserRedirect($userType)
    {
        $routes = [
            'healthMinistry' => '/admin',
            'doctor' => '/doctorhome',
            'hospital' => '/hospital',
            'patient' => '/PatientHomeScreen'
        ];

        return $routes[$userType] ?? '/dashboard';
    }

    // ✅ 4️⃣ تجديد التوكن (Refresh Token)
    public function refreshToken(Request $request)
    {
        $token = $request->bearerToken(); // الحصول على التوكن الحالي من الـ Authorization Header
        if (!$token) {
            return response()->json(['message' => 'Unauthorized: Token missing'], 401);
        }

        try {
            // التحقق من صلاحية التوكن
            $user = JWTAuth::authenticate($token);
            if (!$user) {
                return response()->json(['message' => 'Unauthorized: Invalid token'], 401);
            }

            // إنشاء توكن جديد
            $newToken = JWTAuth::fromUser($user);

            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل في تجديد التوكن'], 500);
        }
    }
    
    // ✅ 5️⃣ تسجيل خروج المستخدم
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken()); // إبطال التوكن
            return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل في تسجيل الخروج'], 500);
        }
    }
}
