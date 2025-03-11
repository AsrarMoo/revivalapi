<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HealthMinistryController;
use App\Http\Controllers\UserController;;
use App\Http\Controllers\OTPController;
use App\Services\FirebaseService;

// ✅ إرسال رمز التحقق عبر Firebase
Route::post('/send-code', function (Request $request, FirebaseService $firebaseService) {
    $request->validate([
        'phone' => 'required|string|regex:/^\+\d{1,15}$/',
    ]);

    try {
        return response()->json($firebaseService->sendVerificationCode($request->phone));
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
});

// ✅ مسارات OTP
Route::post('/send-otp', [OTPController::class, 'sendOTP']);
Route::post('/verify-otp', [OTPController::class, 'verifyOTP']);

// ✅ مسارات المصادقة (متاحة بدون تسجيل دخول)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/refresh', [AuthController::class, 'refreshToken']);
});

// ✅ المسارات المحمية (تحتاج إلى توكن للوصول إليها)
Route::middleware('auth:api')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // ✅ إدارة المرضى
    Route::prefix('patients')->group(function () {
        Route::post('/register', [PatientController::class, 'register']);
        Route::get('/', [PatientController::class, 'index']);
        Route::get('/{id}', [PatientController::class, 'show']);
        Route::put('/{id}', [PatientController::class, 'update']);
        Route::delete('/{id}', [PatientController::class, 'destroy']);
    });

    // ✅ إدارة الأطباء
    Route::prefix('doctors')->group(function () {
        Route::post('/register', [DoctorController::class, 'store']);
        Route::get('/', [DoctorController::class, 'index']);
        Route::get('/{id}', [DoctorController::class, 'show']);
        Route::put('/{id}', [DoctorController::class, 'update']);
        Route::delete('/{id}', [DoctorController::class, 'destroy']);
    });

    // ✅ إدارة المستشفيات
    Route::prefix('hospitals')->group(function () {
        Route::post('/register', [HospitalController::class, 'store']);
        Route::get('/', [HospitalController::class, 'index']);
        Route::get('/{id}', [HospitalController::class, 'show']);
        Route::put('/{id}', [HospitalController::class, 'update']);
        Route::delete('/{id}', [HospitalController::class, 'destroy']);
    });

    // ✅ إدارة وزارة الصحة
    Route::prefix('health_ministry')->group(function () {
        Route::post('/register', [HealthMinistryController::class, 'store']);
        Route::get('/', [HealthMinistryController::class, 'index']);
        Route::get('/{id}', [HealthMinistryController::class, 'show']);
        Route::put('/{id}', [HealthMinistryController::class, 'update']);
        Route::delete('/{id}', [HealthMinistryController::class, 'destroy']);
    });
    
}); Route::prefix('users')->group(function () {
    Route::post('/register', [UserController::class, 'store']); // إضافة مستخدم جديد
    Route::get('/', [UserController::class, 'index']); // استعراض جميع المستخدمين
    Route::get('/{id}', [UserController::class, 'show']); // استعراض مستخدم محدد
    Route::put('/{id}', [UserController::class, 'update']); // تعديل بيانات المستخدم
    Route::delete('/{id}', [UserController::class, 'destroy']); // حذف مستخدم
});

