<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{PatientController, AuthController, DoctorController, 
    HospitalController, HealthMinistryController, UserController,
     OTPController, TipController, TipLikeController, NotificationController, 
     SpecialtyController, HospitalDoctorRequestController,
      HospitalDoctorRequestApprovalController , ScheduleController ,AppointmentController,
      MedicalRecordController,MedicationController,TestController,
      EmergencyController,AmbulanceRescueController,DoctorRatingController,PendingDoctorController};


// ✅ مسارات المصادقة (التسجيل وتسجيل الدخول)
Route::prefix('auth')->group(function () {
    Route::post('/register', [PatientController::class, 'register']); // تسجيل مريض
    Route::post('/login', [AuthController::class, 'login']);
   
    Route::post('/refresh', [AuthController::class, 'refreshToken']);
    Route::post('/register/doctor', [DoctorController::class, 'registerDoctor']);
    Route::get('specialties/list', [SpecialtyController::class, 'getSpecialties']);
});
 Route::get('specialties/doctor-count', [SpecialtyController::class, 'getSpecialtiesWithDoctorCount']);
 Route::get('/countpatient', [PatientController::class, 'getPatientCount']);
 Route::get('/counthospital', [HospitalController::class, 'countHospital']);
 Route::get('/counthospitaldoctor', [HospitalDoctorRequestController::class, 'countHospitalDoctor']);
 Route::get('/doctorrating', [DoctorRatingController::class, 'getAllDoctorsRating']);
 Route::get('/statistics', [PatientController::class, 'genderStatistics']);
 Route::get('/hospital/dashboard', [HospitalController::class, 'dashboardStats']);
 Route::get('/appointments/stats', [HospitalController::class, 'getAppointmentStats']);
 Route::get('/appointments/monthly-stats', [HospitalController::class, 'getMonthlyStats']);
 Route::get('/appointments/available-years', [HospitalController::class, 'getAvailableYears']);
 Route::get('/hospital/patient', [HospitalController::class, 'getPatientsWithAppointments']);
 Route::get('/doctor-hospital', [DoctorController::class, 'getDoctorsWithDetails']);






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
        Route::post('/create', [DoctorController::class, 'create']);
        Route::get('/', [DoctorController::class, 'index']);
        Route::get('/profile', [DoctorController::class, 'show']);
        Route::post('/{id}', [DoctorController::class, 'update']);
        Route::delete('/{id}', [DoctorController::class, 'destroy']);
        Route::put('/approve-doctor/{request_id}', [DoctorController::class, 'approveDoctor']);
        Route::put('/reject-doctor/{request_id}', [DoctorController::class, 'rejectDoctor']);
        Route::get('/hospitals', [DoctorController::class, 'getHospitals']);      
        Route::get('/image', [DoctorController::class, 'simpleDoctors']);
        Route::get('/count', [DoctorController::class, 'countDoctors']);
        Route::get('/{id}', [DoctorController::class, 'showById']);
      

        
       


       

        

    }); //Route::get('/profile', [DoctorController::class, 'profile']);
    Route::post('/rate-doctor/{doctor_id}', [DoctorRatingController::class, 'rateDoctor']);   
    // ✅ إدارة المستشفيات
    Route::prefix('hospitals')->group(function () {
        Route::post('/register', [HospitalController::class, 'register']);
        Route::get('/', [HospitalController::class, 'index']);
        Route::get('/{id}', [HospitalController::class, 'show']);
       

        Route::put('/{id}', [HospitalController::class, 'update']);
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
        Route::put('{notification_id}/{action}', [HospitalDoctorRequestApprovalController::class, 'updateDoctorRequestStatus']); // قبول طلب المستشفى
        Route::put('{notification_id}/reject', [HospitalDoctorRequestApprovalController::class, 'rejectDoctorRequest']); // رفض طلب المستشفى
        Route::get('/doctors', [HospitalDoctorRequestApprovalController::class, 'getHospitalDoctors']);

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
        Route::get('/tops', [TipController::class, 'toptip']);
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
        Route::get('/', [SpecialtyController::class, 'index']);
        Route::get('/{id}', [SpecialtyController::class, 'show']);
        Route::put('/{id}', [SpecialtyController::class, 'update']);
        Route::delete('/{id}', [SpecialtyController::class, 'destroy']);
       
    });
    // ✅ إدارة المواعيد
Route::prefix('schedules')->group(function () {
    Route::get('/', [ScheduleController::class, 'index']); // جلب جميع المواعيد الخاصة بالطبيب
    Route::post('/create', [ScheduleController::class, 'store']); // إضافة موعد جديد
    Route::get('/doctor/hospitals', [ScheduleController::class, 'getDoctorHospitals']);
    Route::get('/{id}', [ScheduleController::class, 'show']);//عرض تفاصيل موعد معين
    Route::put('/{id}', [ScheduleController::class, 'update']); // تعديل موعد وإرسال إشعار للمستشفى
    Route::delete('/{id}', [ScheduleController::class, 'destroy']); // حذف موعد
    Route::post('/review/{notificationId}', [ScheduleController::class, 'reviewSchedule']);//قبول تعديل  موعد
    Route::post('/rejectedreview/{notificationId}', [ScheduleController::class, 'rejectScheduleEdit']);//رفض اعديل موعد
   
});
// ✅ إدارة الحجوزات
Route::prefix('appointments')->group(function () {
    Route::get('/', [AppointmentController::class, 'index']); // جلب جميع الحجوزات
    Route::post('/create', [AppointmentController::class, 'store']); // إضافة حجز جديد
    //Route::get('/{id}', [AppointmentController::class, 'show']); // عرض تفاصيل حجز معين
    Route::put('/{id}', [AppointmentController::class, 'update']); // تعديل الحجز (مثل تغيير الحالة)
    Route::delete('/{id}', [AppointmentController::class, 'destroy']); // حذف الحجز
    Route::post('/confirm/{notificationId}', [AppointmentController::class, 'confirmAppointment']);
    Route::post('/reject/{notificationId}', [AppointmentController::class, 'rejectAppointment']);
    
    Route::get('/hospital/record', [AppointmentController::class, 'getHospitalAppointments']);
    Route::get('/doctor/record', [AppointmentController::class, 'getDoctorAppointments']);
    Route::delete('{appointmentId}/cancel', [AppointmentController::class, 'cancelAppointment']);
    Route::get('/patient', [AppointmentController::class, 'getPatientAppointments']);
    Route::post('{id}/complete', [AppointmentController::class, 'completeAppointment']);


    Route::get('/doctor/{doctorId}/schedules', [ScheduleController::class, 'showDoctorSchedules']);
    

    //Route::post('/review/{id}', [AppointmentController::class, 'reviewAppointment']); // مراجعة حالة الحجز (مثلاً قبول أو رفض)
   // Route::get('/hospital/{hospital_id}', [AppointmentController::class, 'getHospitalAppointments']); // جلب جميع الحجوزات الخاصة بالمستشفى
   //Route::get('/doctor/appointments', [AppointmentController::class, 'getAppointmentsForDoctor']);

// مسار عرض الحجوزات الخاصة بالمستشفى
Route::get('/hospital/appointments', [AppointmentController::class, 'getAppointmentsForHospital']);
});


Route::prefix('medical-records')->group(function () {
   // روت لإنشاء سجل طبي جديد
   Route::post('/', [MedicalRecordController::class, 'storeMedicalRecordAndTests']);
   Route::get('/patients', [MedicalRecordController::class, 'getPatientsByDoctor']);
   // روت لجلب قائمة أسماء المرضى الخاصة بالمشفى
   Route::get('/hospital/patient', [MedicalRecordController::class, 'getHospitalPatients']);
   // روت لجلب تواريخ السجلات الطبية للمريض الخاصة بالمشفى
   Route::get('/hospital/patient/{patientId}/date', [MedicalRecordController::class, 'getPatientRecordsDates']);
   // روت لعرض  نفاصيل السجل الطبي للمشفى 
   Route::get('/hospital/record/{medical_record_id}', [MedicalRecordController::class, 'getHospitalRecordDetails']);


   // روت لجلب قائمة أسماء المرضى الخاصة بالطبيب
   Route::get('/doctor/patient', [MedicalRecordController::class, 'getDoctorPatients']);
   // روت لجلب تواريخ السجلات الطبية للمريض الخاصة بالطبيب
   Route::get('/doctor/patient/{patientId}/date', [MedicalRecordController::class, 'getDoctorPatientRecordsDates']);
   // روت لعرض  نفاصيل السجل الطبي للطبيب 
   Route::get('/doctor/record/{medical_record_id}', [MedicalRecordController::class, 'getDoctorRecordDetails']);
//روت لعرض تواريخ السجلات الطبية للمريض
   Route::get('/patient/record/dates', [MedicalRecordController::class, 'getPatientRecordsDatesforpatient']);
//روت لعرض تفاصيل السجل الطبي للمريض
   Route::get('/patient/record/{medical_record_id}', [MedicalRecordController::class, 'getPatientRecordDetailsforpatient']);

  
});








Route::prefix('medications')->group(function () {
Route::get('/', [MedicationController::class, 'index']);
Route::get('/names', [MedicationController::class, 'getMedicationNames']);
Route::post('/', [MedicationController::class, 'store']);
Route::put('/{id}', [MedicationController::class, 'update']);
Route::delete('/{id}', [MedicationController::class, 'destroy']);

});

Route::prefix('tests')->group(function () {
Route::get('/', [TestController::class, 'index']);
Route::get('/names', [TestController::class, 'getTestNames']);
Route::post('/', [TestController::class, 'store']);
Route::put('/{id}', [TestController::class, 'update']);
Route::delete('/{id}', [TestController::class, 'destroy']);
});




Route::prefix('ambulance-request')->group(function() {
    //ارسال طلب اسعاف
    Route::post('find-nearest-hospitals', [EmergencyController::class, 'findNearestHospitals']);
    // قبول طلب الإسعاف
    Route::post('{notificationId}/accept', [EmergencyController::class, 'acceptAmbulanceRequest']);

    
    // رفض طلب الإسعاف
    Route::post('{notificationId}/reject', [EmergencyController::class, 'rejectAmbulanceRequest']);
    
   // ملف routes/api.php
Route::get('/hospital/rescued-patients', [AmbulanceRescueController::class, 'getPatientDataForHospital']);
//اسعاف شخص اخر
Route::post('ambulance-request/send', [EmergencyController::class, 'findNearestHospitalsForAnotherPatient']);

Route::post('/accept-other/{notificationId}', [EmergencyController::class, 'acceptAmbulanceRequestForOther']);

Route::put('/ambulance/{rescueId}/mark-fake', [EmergencyController::class, 'markFakeAmbulanceRequest']);
Route::put('unban-user/{id}', [EmergencyController::class, 'unbanUser']);

});
Route::prefix('pending')->group(function() {
    Route::get('/doctors', [PendingDoctorController::class, 'index']);
});

});
