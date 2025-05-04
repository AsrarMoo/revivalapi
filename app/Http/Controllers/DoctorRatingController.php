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
        // التحقق من تسجيل الدخول
        $patient = Auth::user();
        
        // التحقق من وجود الحجز
        $appointment = Appointment::where('doctor_id', $doctor_id)
                                   ->where('patient_id', $patient->user_id)
                                   ->whereIn('status', ['Confirmed', 'Completed']) // التأكد من أن الحالة "مؤكد" أو "مكتمل"
                                   ->first();
    
        if (!$appointment) {
            return response()->json(['error' => 'لم تجد حجزًا مع الطبيب هذا.'], 404);
        }

        // جلب معلومات الموعد من جدول المواعيد المرتبط
        $schedule = $appointment->schedule;  // Assuming the relationship is defined in Appointment model
        
        if (!$schedule) {
            return response()->json(['error' => 'لم يتم العثور على الموعد المرتبط بهذا الحجز.'], 404);
        }
        
        // استخدم وقت بداية الموعد (start_time) من جدول المواعيد
        $appointmentTime = Carbon::parse($schedule->start_time); // تأكد من أن "start_time" هو اسم العمود في جدول المواعيد
        $timeDifference = now()->diffInHours($appointmentTime);
    
        if ($timeDifference > 24) {
            return response()->json(['error' => 'لقد تجاوزت الوقت المسموح به للتقييم (24 ساعة من وقت الموعد).'], 400);
        }
    
        // التحقق من أن المريض لم يقيم من قبل
        $ratingExists = DoctorRating::where('appointment_id', $appointment->appointment_id)
                                    ->exists();
    
        if ($ratingExists) {
            return response()->json(['error' => 'لقد قمت بتقييم هذا الطبيب من قبل.'], 400);
        }
    
        // إنشاء التقييم الجديد
        $rating = DoctorRating::create([
            'appointment_id' => $appointment->appointment_id,
            'patient_id' => $patient->user_id,
            'doctor_id' => $doctor_id,
            'professionalism' => $request->professionalism,
            'communication' => $request->communication,
            'listening' => $request->listening,
            'knowledge_experience' => $request->knowledge_experience,
            'punctuality' => $request->punctuality,
        ]);

        // إنشاء الإشعار للطبيب
        $notification = Notification::create([
            'user_id' => $doctor_id, // الطبيب الذي سيستقبل الإشعار
            'created_by' => $patient->user_id, // المريض الذي قام بالتقييم
            'title' => 'تم تقييمك', 
            'message' => 'لقد قام المريض بتقييمك. تحقق من تقييمك.',
            'type' => 'general',
            'is_read' => 0, // الإشعار غير مقروء بعد
        ]);

        return response()->json(['message' => 'تم التقييم بنجاح. تم إرسال إشعار للطبيب.'], 200);
    }
  // دالة لعرض اسم الطبيب والتقييم الكلي
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
