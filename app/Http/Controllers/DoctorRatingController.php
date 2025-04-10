<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorRating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Log;

class DoctorRatingController extends Controller
{public function rateDoctor(Request $request, $doctor_id)
    {
        // التحقق من تسجيل الدخول
        $patient = Auth::user();
        
        // التحقق من وجود الحجز
        $appointment = Appointment::where('doctor_id', $doctor_id)
                                   ->where('patient_id', $patient->user_id)
                                   ->where('status', 'Confirmed') // التأكد من أن الحالة "مؤكد"
                                   ->first();
    
        if (!$appointment) {
            return response()->json(['error' => 'لم تجد حجزًا مع الطبيب هذا.'], 404);
        }
    
        // التحقق من مرور 24 ساعة على الحجز
        $timeDifference = now()->diffInHours($appointment->created_at);
    
        if ($timeDifference > 24) {
            return response()->json(['error' => 'لقد تجاوزت الوقت المسموح به للتقييم (24 ساعة).'], 400);
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
    
        return response()->json(['message' => 'تم التقييم بنجاح.'], 200);
    }
    
    
}
