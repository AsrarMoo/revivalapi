<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\TwilioService;

class OTPController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * إرسال رمز التحقق إلى رقم الهاتف عبر واتساب
     */
    public function sendOTP(Request $request)
    {
        // التحقق من البيانات المدخلة
        $request->validate([
            'patient_phone' => 'required|string|unique:patients,patient_phone', // يجب أن يكون الرقم غير مكرر
            'patient_name' => 'required|string', // يجب إدخال اسم المريض
            'email' => 'required|email|unique:users,email', // يجب أن يكون البريد الإلكتروني فريدًا
            'password' => 'required|min:6', // يجب أن تكون كلمة المرور لا تقل عن 6 أحرف
        ]);

        // تنسيق الرقم مع رمز البلد (هنا نفترض رمز البلد هو +967 لليمن)
        $phoneWithCountryCode = '+967' . $request->patient_phone;

        // تخزين بيانات المستخدم مؤقتًا في الكاش لمدة 10 دقائق
        $userData = [
            'patient_name' => $request->patient_name,
            'email' => $request->email,
            'patient_phone' => $request->patient_phone,
            'password' => Hash::make($request->password), // تشفير كلمة المرور
        ];
        Cache::put('pending_user_' . $request->patient_phone, $userData, now()->addMinutes(10));

        // إنشاء رمز تحقق عشوائي وحفظه في الكاش لمدة 5 دقائق
        $otp = rand(100000, 999999);
        Cache::put('otp_' . $request->patient_phone, $otp, now()->addMinutes(5));

        // إرسال رمز التحقق عبر Twilio
        try {
            $this->twilioService->sendOTP($phoneWithCountryCode, $otp); // إرسال OTP عبر Twilio
            return response()->json(['message' => 'تم إرسال رمز التحقق بنجاح'], 200);
        } catch (\Exception $e) {
            // في حالة حدوث خطأ أثناء إرسال OTP
            Log::error("فشل إرسال رمز التحقق: " . $e->getMessage());
            return response()->json(['message' => 'فشل إرسال رمز التحقق', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * التحقق من رمز OTP وإنشاء الحساب
     */
    public function verifyOTP(Request $request)
    {
        // التحقق من البيانات المدخلة
        $request->validate([
            'patient_phone' => 'required|string', // رقم الهاتف مطلوب
            'otp' => 'required|numeric' // رمز OTP مطلوب ويجب أن يكون رقميًا
        ]);

        // جلب رمز التحقق المخزن من الكاش
        $storedOTP = Cache::get('otp_' . $request->patient_phone);
        if (!$storedOTP || $storedOTP != $request->otp) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية'], 400);
        }

        // جلب بيانات المستخدم المخزنة مؤقتًا من الكاش
        $userData = Cache::get('pending_user_' . $request->patient_phone);
        if (!$userData) {
            return response()->json(['message' => 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة'], 400);
        }

        try {
            // إنشاء المريض أولاً وحفظ `patient_id`
            $patient = \App\Models\Patient::create([
                'patient_name' => $userData['patient_name'],
                'patient_phone' => $userData['patient_phone'],
                'patient_email' => $userData['email']
                // يمكن إضافة المزيد من الحقول لاحقًا
            ]);

            // إنشاء المستخدم وربطه بـ `patient_id`
            $user = \App\Models\User::create([
                'patient_name' => $userData['patient_name'],
                'email' => $userData['email'],
                'patient_phone' => $userData['patient_phone'],
                'password' => $userData['password'],
                'user_type' => 'patient', // تحديد أنه مريض
                'patient_id' => $patient->patient_id
            ]);

        } catch (\Exception $e) {
            // في حالة حدوث خطأ أثناء إنشاء الحساب
            Log::error("خطأ أثناء إنشاء المستخدم أو المريض: " . $e->getMessage());
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء الحساب', 'error' => $e->getMessage()], 500);
        }

        // حذف البيانات المؤقتة من الكاش بعد التسجيل الناجح
        Cache::forget('otp_' . $request->patient_phone);
        Cache::forget('pending_user_' . $request->patient_phone);

        // العودة برسالة النجاح مع تفاصيل المستخدم والمريض
        return response()->json([
            'message' => 'تم التحقق بنجاح، يرجى إكمال بياناتك',
            'user' => $user,
            'patient' => $patient // سيتم استخدام `patient_id` في الواجهة الثانية
        ], 201);
    }
}
