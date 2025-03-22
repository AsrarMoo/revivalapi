<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{PatientController, AuthController, DoctorController, 
    HospitalController, HealthMinistryController, UserController,
     OTPController, TipController, TipLikeController, NotificationController, 
     SpecialtyController, HospitalDoctorRequestController,
      HospitalDoctorRequestApprovalController , ScheduleController ,AppointmentController};
use App\Services\FirebaseService;

// ✅ إرسال رمز التحقق عبر Firebase
Route::post('/send-code', function (Request $request, FirebaseService $firebaseService) {
    $request->validate([
        'phone' => 'required|string|regex:/^\\+\\d{1,15}$/',
    ]);
    return response()->json(['message' => 'OTP Sent']);
});

// ✅ مسارات التحقق عبر OTP
Route::prefix('otp')->group(function () {
    Route::post('/send', [OTPController::class, 'sendOTP']);
    Route::post('/verify', [OTPController::class, 'verifyOTP']);
});

// ✅ مسارات المصادقة (التسجيل وتسجيل الدخول)
Route::prefix('auth')->group(function () {
    Route::post('/register', [PatientController::class, 'register']); // تسجيل مريض
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refreshToken']);
});

// ✅ المسارات المحمية (تتطلب توكن)
Route::middleware('auth:api')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // ✅ إدارة المرضى
    Route::prefix('patients')->group(function () {
        Route::get('/', [PatientController::class, 'index']);
        Route::get('/{id}', [PatientController::class, 'show']);
        Route::post('/{id}', [PatientController::class, 'update']);
        Route::delete('/{id}', [PatientController::class, 'destroy']);
    });
    Route::get('/profile', [PatientController::class, 'getProfile']);

    // ✅ إدارة الأطباء
    Route::prefix('doctors')->group(function () {
        Route::post('/register', [DoctorController::class, 'register']);
        Route::get('/', [DoctorController::class, 'index']);
        Route::get('/{id}', [DoctorController::class, 'show']);
        Route::post('/{id}', [DoctorController::class, 'update']);
        Route::delete('/{id}', [DoctorController::class, 'destroy']);
    });

    // ✅ إدارة المستشفيات
    Route::prefix('hospitals')->group(function () {
        Route::post('/register', [HospitalController::class, 'register']);
        Route::get('/', [HospitalController::class, 'index']);
        Route::get('/{id}', [HospitalController::class, 'show']);
        Route::post('/{id}', [HospitalController::class, 'update']);
        Route::delete('/{id}', [HospitalController::class, 'destroy']);
    });

    // ✅ إدارة طلبات إضافة المستشفيات
    Route::prefix('hospital-requests')->group(function () {
        Route::post('/', [HospitalDoctorRequestController::class, 'requestDoctor']); // تقديم طلب إضافة مستشفى
        Route::get('/', [HospitalDoctorRequestController::class, 'index']); // مشاهدة جميع الطلبات
        Route::get('/{id}', [HospitalDoctorRequestController::class, 'show']); // مشاهدة تفاصيل طلب معين
        Route::delete('/{id}', [HospitalDoctorRequestController::class, 'destroy']); // حذف طلب
    });

    // ✅ إدارة الموافقات من وزارة الصحة
    Route::prefix('hospital-approvals')->group(function () {
        Route::put('/{id}', [HospitalDoctorRequestApprovalController::class, 'updateDoctorRequestStatus']); // قبول طلب المستشفى
       
        Route::get('/pending', [HospitalDoctorRequestApprovalController::class, 'pendingRequests']); // مشاهدة جميع الطلبات المعتمدة أو المرفوضة
    });
    //✅ عرض اسماء المستشفيات والاطباء المتاحين 
    Route::get('/doctor-hospital-requests', [HospitalDoctorRequestController::class, 'getDoctorHospitalRequests']);
    Route::get('/doctor-hospitals', [HospitalDoctorRequestController::class, 'getDoctorHospitals']);

    // ✅ إدارة وزارة الصحة
    Route::prefix('health-ministry')->group(function () {
        Route::post('/register', [HealthMinistryController::class, 'store']);
        Route::get('/', [HealthMinistryController::class, 'index']);
        Route::get('/{id}', [HealthMinistryController::class, 'show']);
        Route::put('/{id}', [HealthMinistryController::class, 'update']);
        Route::delete('/{id}', [HealthMinistryController::class, 'destroy']);
    });

    // ✅ إدارة المستخدمين
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/names', [UserController::class, 'getNames']);
        Route::get('/types', [UserController::class, 'getUserTypes']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });

    // ✅ إدارة النصائح (Tips)
    Route::prefix('tips')->group(function () {
        Route::post('/', [TipController::class, 'store']);
        Route::get('/', [TipController::class, 'index']);
        Route::get('/{id}', [TipController::class, 'show']);
        Route::put('/{id}', [TipController::class, 'update']);
        Route::delete('/{id}', [TipController::class, 'destroy']);
    });

    // ✅ إدارة متابعة النصائح (Tips)
    Route::prefix('like')->group(function () {
        Route::post('/{tip_id}', [TipLikeController::class, 'likeTip']);
        Route::delete('/{tip_id}', [TipLikeController::class, 'unlikeTip']);
        Route::get('/tips', [TipLikeController::class, 'getTips']);
    });

    // ✅ إدارة الإشعارات
    Route::prefix('notifications')->group(function () {
        Route::post('/', [NotificationController::class, 'store']);
        Route::get('/user', [NotificationController::class, 'getUserNotifications']);
        Route::get('/all', [NotificationController::class, 'getAllNotifications']);
        Route::get('/needed', [NotificationController::class, 'getUserNotificationsview']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // ✅ إدارة التخصصات
    Route::prefix('specialties')->group(function () {
        Route::post('/create', [SpecialtyController::class, 'store']);
        Route::get('/list', [SpecialtyController::class, 'getSpecialties']);
        Route::get('/', [SpecialtyController::class, 'index']);
        Route::get('/{id}', [SpecialtyController::class, 'show']);
        Route::put('/{id}', [SpecialtyController::class, 'update']);
        Route::delete('/{id}', [SpecialtyController::class, 'destroy']);
    });
    // ✅ إدارة المواعيد
Route::prefix('schedules')->group(function () {
    Route::get('/', [ScheduleController::class, 'index']); // جلب جميع المواعيد الخاصة بالطبيب
    Route::post('/create', [ScheduleController::class, 'store']); // إضافة موعد جديد
    Route::put('/{id}', [ScheduleController::class, 'update']); // تعديل موعد وإرسال إشعار للمستشفى
    Route::delete('/{id}', [ScheduleController::class, 'destroy']); // حذف موعد
    Route::post('/review/{id}', [ScheduleController::class, 'reviewSchedule']);

});
// ✅ إدارة الحجوزات
Route::prefix('appointments')->group(function () {
    Route::get('/', [AppointmentController::class, 'index']); // جلب جميع الحجوزات
    Route::post('/create', [AppointmentController::class, 'store']); // إضافة حجز جديد
    Route::get('/{id}', [AppointmentController::class, 'show']); // عرض تفاصيل حجز معين
    Route::put('/{id}', [AppointmentController::class, 'update']); // تعديل الحجز (مثل تغيير الحالة)
    Route::delete('/{id}', [AppointmentController::class, 'destroy']); // حذف الحجز
    Route::post('/review/{id}', [AppointmentController::class, 'reviewAppointment']); // مراجعة حالة الحجز (مثلاً قبول أو رفض)
    Route::get('/hospital/{hospital_id}', [AppointmentController::class, 'getHospitalAppointments']); // جلب جميع الحجوزات الخاصة بالمستشفى
});
});
