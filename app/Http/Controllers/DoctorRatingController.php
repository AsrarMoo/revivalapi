<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorRating;
use App\Models\Notification; // إضافة هذا السطر لاستيراد موديل الإشعارات
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Log;

class DoctorRatingController extends Controller
{
    public function rateDoctor(Request $request, $doctor_id)
{
    $user = Auth::user();

    // تأكد أن نوع المستخدم مريض
    if (!$user || $user->user_type !== 'patient') {
        return response()->json(['error' => 'فقط المرضى يمكنهم تقييم الأطباء.'], 403);
    }

    // جلب patient_id المرتبط بـ user_id
    $patient = Patient::where('user_id', $user->user_id)->first();

    if (!$patient) {
        return response()->json(['error' => 'لم يتم العثور على المريض المرتبط بهذا المستخدم.'], 404);
    }

    // البحث عن حجز مكتمل
    $appointment = Appointment::where('doctor_id', $doctor_id)
                               ->where('patient_id', $patient->patient_id)
                               ->where('status', 'Completed')
                               ->first();

    if (!$appointment) {
        return response()->json(['error' => 'لم يتم العثور على حجز مكتمل مع هذا الطبيب.'], 404);
    }

    // التحقق من وجود الموعد المرتبط
    $schedule = $appointment->schedule;

    if (!$schedule) {
        return response()->json(['error' => 'لم يتم العثور على الموعد المرتبط بالحجز.'], 404);
    }

    // تحقق من الوقت المسموح للتقييم
    $appointmentTime = Carbon::parse($schedule->start_time);
    $timeDifference = now()->diffInHours($appointmentTime);

    if ($timeDifference > 24) {
        return response()->json(['error' => 'لقد تجاوزت الوقت المسموح به للتقييم (24 ساعة من وقت الموعد).'], 400);
    }

    // التحقق من وجود تقييم مسبق
    $ratingExists = DoctorRating::where('appointment_id', $appointment->appointment_id)->exists();

    if ($ratingExists) {
        return response()->json(['error' => 'لقد قمت بتقييم هذا الطبيب من قبل.'], 400);
    }

    // إنشاء التقييم
    DoctorRating::create([
        'appointment_id' => $appointment->appointment_id,
        'patient_id' => $patient->patient_id,
        'doctor_id' => $doctor_id,
        'professionalism' => $request->professionalism,
        'communication' => $request->communication,
        'listening' => $request->listening,
        'knowledge_experience' => $request->knowledge_experience,
        'punctuality' => $request->punctuality,
    ]);

    // إرسال إشعار للطبيب
    Notification::create([
        'user_id' => $doctor_id,
        'created_by' => $user->user_id,
        'title' => 'تم تقييمك',
        'message' => 'لقد قام المريض بتقييمك. تحقق من تقييمك.',
        'type' => 'general',
        'is_read' => 0,
    ]);

    return response()->json(['message' => 'تم التقييم بنجاح.'], 200);
}

// دالة لعرض اسم الطبيب والتقييم الكلي
public function getAllDoctorsRating()
{
    // جلب جميع الأطباء مع التقييمات المرتبطة بهم
    $doctors = Doctor::with('doctor_rataing')->get();  // استخدم العلاقة الصحيحة 'doctor_rataing'

    // التحقق إذا كان هناك أطباء
    if ($doctors->isEmpty()) {
        return response()->json(['message' => 'لا يوجد أطباء في النظام.'], 200);
    }

    // تحضير البيانات للإرجاع مع التقييمات
    $doctorRatings = $doctors->map(function ($doctor) {
        // تحقق إذا كان الطبيب لديه تقييمات
        $overallRating = $doctor->doctor_rataing->isNotEmpty() 
                         ? $doctor->doctor_rataing->pluck('overall_rating')->first() 
                         : 'لا يوجد تقييم'; 

        return [
            'doctor_name' => $doctor->doctor_name,
        //    'specialty' => $doctor->specialty,
            'overall_rating' => $overallRating, // التقييم الكلي المحسوب مسبقًا أو القيمة الافتراضية
        ];
    });

    return response()->json($doctorRatings, 200);
}


  
}
