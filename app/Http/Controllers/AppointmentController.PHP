<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Notification;

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
        Log::info('🔹 بدء تنفيذ دالة store لحجز موعد جديد');
    
        $user = Auth::user();
        Log::info('🧑‍💼 المستخدم الحالي:', ['user' => $user]);
    
        $userType = DB::table('users')->where('user_id', $user->user_id)->value('user_type');
        Log::info('🛠 نوع المستخدم:', ['user_type' => $userType]);
    
        if ($userType !== 'patient') {
            Log::warning('🚫 محاولة حجز من مستخدم غير مريض!', ['user_id' => $user->user_id]);
            return response()->json(['message' => 'مسموح فقط للمرضى بحجز المواعيد'], 403);
        }
    
        $patient = DB::table('patients')->where('user_id', $user->user_id)->first();
        
        if (!$patient) {
            Log::error('❌ لم يتم العثور على بيانات المريض!', ['user_id' => $user->user_id]);
            return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
        }
    
        Log::info('✅ تم العثور على بيانات المريض:', ['patient' => $patient]);
    
        $validatedData = $request->validate([
            'hospital_id' => 'required|exists:hospitals,hospital_id',
            'doctor_id' => 'required|exists:doctors,doctor_id',
            'schedule_id' => 'required|exists:doctor_schedules,schedule_id',
        ]);
    
        Log::info('✅ البيانات المدخلة صحيحة:', ['request_data' => $validatedData]);
    
        $appointment = Appointment::create([
            'patient_id' => $patient->patient_id,
            'hospital_id' => $request->hospital_id,
            'doctor_id' => $request->doctor_id,
            'schedule_id' => $request->schedule_id,
            'status' => 'Pending',
        ]);
    
        Log::info('📅 تم إنشاء الحجز بنجاح:', ['appointment' => $appointment]);
    
        $doctor = DB::table('doctors')->where('doctor_id', $request->doctor_id)->first();
        $schedule = DB::table('doctor_schedules')->where('schedule_id', $request->schedule_id)->first();
    
        $hospitalUserId = DB::table('users')
            ->where('hospital_id', $request->hospital_id)
            ->value('user_id');
    
        if ($hospitalUserId && $doctor && $schedule) {
            $startDate = Carbon::parse($schedule->start_time)->translatedFormat('l d F Y الساعة h:i A');
            $endDate = Carbon::parse($schedule->end_time)->translatedFormat('h:i A');
    
            Log::info('📢 إرسال إشعار للمستشفى:', [
                'hospital_user_id' => $hospitalUserId,
                'patient_name' => $patient->patient_name,
                'doctor_name' => $doctor->doctor_name,
                'appointment_time' => "$startDate - $endDate"
            ]);
    
            $this->sendNotification($hospitalUserId, 'طلب حجز جديد', 'المريض ' . $patient->patient_name . ' حجز موعد مع الدكتور ' . $doctor->doctor_name .
                ' من ' . $startDate . ' إلى ' . $endDate . '، يرجى المراجعة والموافقة.');
        }
    
        Log::info('✅ تم تنفيذ الدالة بنجاح، بانتظار موافقة المستشفى.');
    
        return response()->json([
            'message' => 'تم إنشاء الحجز بنجاح، في انتظار موافقة المستشفى',
            'appointment' => $appointment
        ], 201);
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

