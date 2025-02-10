<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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

        // التوجيه حسب نوع المستخدم
        $redirectUrl = '';
        switch ($user->user_type) {
            case 'Admin':
                $redirectUrl = '/admin/dashboard';
                break;
            case 'Doctor':
                $redirectUrl = '/doctor/dashboard';
                break;
            case 'Hospital':
                $redirectUrl = '/hospital/dashboard';
                break;
            case 'Patient':
                $redirectUrl = '/patient/dashboard';
                break;
            default:
                return response()->json(['error' => 'Invalid user type'], 400);
        }

        // إرجاع استجابة تسجيل الدخول
        return response()->json([
            'message' => 'Login successful',
            'redirect_url' => $redirectUrl,
            'user' => $user,
        ], 200);
    }
}