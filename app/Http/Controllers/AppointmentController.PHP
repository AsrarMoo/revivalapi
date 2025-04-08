<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Notification;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Schedule;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index()
    {
        return response()->json(Appointment::with(['patient', 'hospital', 'doctor', 'schedule'])->get());
    }

    public function store(Request $request)
{
    $validatedData = $request->validate([
        'doctor_name' => 'required|string',
        'hospital_name' => 'required|string',
        'day_of_week' => 'required|string',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i',
    ]);

    // البحث عن الطبيب
    $doctor = Doctor::whereRaw('LOWER(doctor_name) = ?', [strtolower($validatedData['doctor_name'])])->first();
    if (!$doctor) {
        return response()->json(['error' => 'اسم الطبيب غير موجود.'], 404);
    }

    // البحث عن المستشفى
    $hospital = Hospital::whereRaw('LOWER(hospital_name) = ?', [strtolower($validatedData['hospital_name'])])->first();
    if (!$hospital) {
        return response()->json(['error' => 'اسم المستشفى غير موجود.'], 404);
    }

    // البحث عن موعد الطبيب بناءً على اليوم والوقت
    $schedule = Schedule::where('doctor_id', $doctor->doctor_id)
                        ->where('hospital_id', $hospital->hospital_id)
                        ->where('day_of_week', $validatedData['day_of_week'])
                        ->where('start_time', $validatedData['start_time'])
                        ->where('end_time', $validatedData['end_time'])
                        ->first();

    if (!$schedule) {
        return response()->json(['error' => 'لا يوجد موعد مطابق لهذا اليوم والوقت.'], 404);
    }

    // حفظ الموعد للمريض
    $appointment = new Appointment();
    $appointment->doctor_id = $doctor->doctor_id;
    $appointment->hospital_id = $hospital->hospital_id;
    $appointment->patient_id = $request->patient_id; // التأكد من أن patient_id موجود في الـ request
    $appointment->schedule_id = $schedule->schedule_id; // ربط الموعد بـ schedule_id
    $appointment->status = 'Pending'; // أو حسب الحاجة
    $appointment->save();

    return response()->json([
        'message' => 'تم إضافة الموعد بنجاح.',
        'data' => $appointment
    ], 201);
}



    public function approveAppointment($appointmentId)
    {
        $appointment = Appointment::find($appointmentId);
    
        // التحقق من أن المستشفى قد وافق على الحجز
        if ($appointment && $appointment->hospital_approval == 'approved') {
            $appointment->status = 'confirmed';
            $appointment->save();
    
            return response()->json(['success' => 'تم تأكيد الحجز.'], 200);
        }
    
        return response()->json(['error' => 'الحجز لم تتم الموافقة عليه من المستشفى.'], 400);
    }
    





















public function respondToAppointment(Request $request, $id)
{
    $request->validate([
        'action' => 'required|in:accept,reject',
    ]);

    $appointment = Appointment::with(['patient', 'doctor'])->findOrFail($id);

    if ($request->action === 'accept') {
        $appointment->status = 'Confirmed';
        $appointment->save();

        // إشعار للمريض
        Notification::create([
            'user_id' => $appointment->patient->user_id,
            'created_by' => auth()->id(),
            'title' => 'تم تأكيد الحجز',
            'message' => 'تم تأكيد حجزك في المستشفى بنجاح.',
            'type' => 'booking',
            'is_read' => 0,
        ]);

        // إشعار للطبيب
        $doctorUser = User::where('doctor_id', $appointment->doctor_id)->first();
        if ($doctorUser) {
            Notification::create([
                'user_id' => $doctorUser->user_id,
                'created_by' => auth()->id(),
                'title' => 'لديك حجز جديد',
                'message' => 'يوجد حجز جديد من المريض ' . $appointment->patient->patient_name,
                'type' => 'booking',
                'is_read' => 0,
            ]);
        }

    } else {
        $appointment->status = 'Rejected';
        $appointment->save();

        // إشعار للمريض بالرفض
        Notification::create([
            'user_id' => $appointment->patient->user_id,
            'created_by' => auth()->id(),
            'title' => 'تم رفض الحجز',
            'message' => 'نأسف، تم رفض طلب الحجز الخاص بك.',
            'type' => 'Rejected',
            'is_read' => 0,
        ]);
    }

    return response()->json(['message' => 'تم تنفيذ الإجراء بنجاح.']);
}















    public function reviewAppointment(Request $request, $id)
    {
        Log::info('🔍 البحث عن الحجز بالمعرف:', ['appointment_id' => $id]);
    
        $appointment = Appointment::where('appointment_id', $id)->first();
        if (!$appointment) {
            Log::error('❌ الحجز غير موجود!', ['appointment_id' => $id]);
            return response()->json(['message' => 'الحجز غير موجود'], 404);
        }
    
        Log::info('✅ الحجز موجود، جاري التحديث...');
    
        $request->validate([
            'status' => 'in:Confirmed,Rejected,Cancelled',
        ]);
    
        $appointment->update(['status' => $request->status]);
    
        Log::info('🔔 إرسال إشعارات للمرضى والأطباء...');
    
        if ($request->status == 'Confirmed') {
            $patientUserId = DB::table('users')->where('patient_id', $appointment->patient_id)->value('user_id');
            $doctorUserId = DB::table('users')->where('doctor_id', $appointment->doctor_id)->value('user_id');
    
            $patient = DB::table('patients')->where('patient_id', $appointment->patient_id)->first();
            $hospital = DB::table('hospitals')->where('hospital_id', $appointment->hospital_id)->first();
            $schedule = DB::table('doctor_schedules')->where('schedule_id', $appointment->schedule_id)->first();
    
            if ($patient && $hospital && $schedule) {
                $startTime = Carbon::parse($schedule->start_time)->format('h:i A');
                $endTime = Carbon::parse($schedule->end_time)->format('h:i A');
    
                $patientName = $patient->patient_name ?? "غير معروف";
                $hospitalName = $hospital->hospital_name ?? "غير معروف";
    
                $patientMessage = "تمت الموافقة على حجزك في المستشفى $hospitalName مع الطبيب.";
                $doctorMessage = "لديك حجز جديد مع المريض $patientName في المستشفى $hospitalName من الساعة $startTime إلى $endTime.";
    
                $this->sendNotification($patientUserId, 'تم تأكيد حجزك', $patientMessage);
                $this->sendNotification($doctorUserId, 'حجز جديد', $doctorMessage);
            }
    
            // ✅ تحقق من عدد الحجوزات المؤكدة لهذا الوقت (schedule_id)
            $confirmedAppointmentsCount = Appointment::where('schedule_id', $appointment->schedule_id)
                ->where('status', 'Confirmed')
                ->count();
    
            if ($confirmedAppointmentsCount >= 15) {
                // إذا وصل العدد إلى 15، قم بتحديث جميع الحجوزات في هذا الوقت إلى "Completed"
                Appointment::where('schedule_id', $appointment->schedule_id)
                    ->where('status', 'Confirmed')
                    ->update(['status' => 'Completed']);
    
                Log::info("🚨 تم إكمال الحجز لهذا الوقت بعد 15 حجز.", ['schedule_id' => $appointment->schedule_id]);
            }
        }
    
        return response()->json(['message' => 'تم تحديث الحجز بنجاح'], 200);
    }
    
public function sendNotification($userId, $title, $message)
{
    Log::info('🚀 إرسال إشعار...', [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message
    ]);

    $user = User::find($userId);
    if (!$user) {
        Log::error('❌ المستخدم غير موجود!', ['user_id' => $userId]);
        return;
    }

    // إرسال الإشعار عبر النظام اليدوي
    Notification::create([
        'user_id' => $user->user_id,
        'title' => $title,
        'message' => $message,
        'status' => 'unread',
    ]);

    Log::info('✅ تم إنشاء الإشعار بنجاح!');
}
public function getAppointmentsForDoctor()
{
    // الحصول على التوكن الحالي
    $user = Auth::user();
    
    // التأكد من أن المستخدم هو طبيب
    $userType = DB::table('users')->where('user_id', $user->user_id)->value('user_type');
    if ($userType !== 'doctor') {
        return response()->json(['message' => 'مسموح فقط للأطباء بعرض الحجوزات'], 403);
    }
    
    // الحصول على الحجوزات الخاصة بالطبيب
    $appointments = Appointment::with(['patient', 'hospital', 'schedule'])
                               ->where('doctor_id', $user->doctor_id)
                               ->get();
    
    return response()->json($appointments);
}
public function getAppointmentsForHospital()
{
    // الحصول على التوكن الحالي
    $user = Auth::user();
    
    // التأكد من أن المستخدم هو مستشفى
    $userType = DB::table('users')->where('user_id', $user->user_id)->value('user_type');
    if ($userType !== 'hospital') {
        return response()->json(['message' => 'مسموح فقط للمستشفيات بعرض الحجوزات'], 403);
    }
    
    // الحصول على جميع الحجوزات الخاصة بالمستشفى
    $appointments = Appointment::with(['patient', 'doctor', 'schedule'])
                               ->where('hospital_id', $user->hospital_id)
                               ->get();
    
    return response()->json($appointments);
}



    }

