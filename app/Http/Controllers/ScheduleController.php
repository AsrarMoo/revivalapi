<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Hospital;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $doctorId = auth()->user()->doctor_id;
        
        // جلب جميع المواعيد للطبيب
        $schedules = Schedule::where('doctor_id', $doctorId)
            ->with([
                'doctor' => function ($query) {
                    $query->select('doctor_id', 'doctor_name');
                },
                'hospital' => function ($query) {
                    $query->select('hospital_id', 'hospital_name');
                }
            ])
            ->get()
            ->map(function ($schedule) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'doctor_name' => $schedule->doctor->doctor_name ?? 'غير معروف',
                    'hospital_name' => $schedule->hospital->hospital_name ?? 'غير معروف',
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
                    'status' => $schedule->status,
                    'created_at' => Carbon::parse($schedule->created_at)->format('Y-m-d h:i A'),
                    'updated_at' => Carbon::parse($schedule->updated_at)->format('Y-m-d h:i A'),
                ];
            });
        
        // إذا لم يكن هناك مواعيد
        if ($schedules->isEmpty()) {
            return response()->json(['message' => 'لا يوجد لديك مواعيد بعد'], 404);
        }
    
        return response()->json($schedules);
    }
    
    
// 🔹 عرض تفاصيل موعد معين
public function show($id)
{
    $schedule = Schedule::where('schedule_id', $id)
    ->with([
        'doctor' => function ($query) {
            $query->select('doctor_id', 'doctor_name');
        },
        'hospital' => function ($query) {
            $query->select('hospital_id', 'hospital_name');
        }
    ])
    ->first();

if (!$schedule) {
    return response()->json(['error' => 'لم يتم العثور على الموعد'], 404);
}


    // إذا تم العثور على الموعد، إرجاع تفاصيله
    return response()->json([
        'schedule_id' => $schedule->schedule_id,
        'doctor_name' => $schedule->doctor->doctor_name ?? 'غير معروف',
        'hospital_name' => $schedule->hospital->hospital_name ?? 'غير معروف',
        'day_of_week' => $schedule->day_of_week,
       'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
        'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
      //  'proposed_start_time' => $schedule->proposed_start_time,
      //  'proposed_end_time' => $schedule->proposed_end_time,
        'status' => $schedule->status,
        'created_at' => Carbon::parse($schedule->created_at)->format('d-m-Y h:i:s A'),
        'updated_at' => Carbon::parse($schedule->updated_at)->format('d-m-Y h:i:s A'),
    ]);
}
public function store(Request $request)
{
    // التحقق من أن اسم المستشفى موجود في جدول hospitals
    $hospital = DB::table('hospitals')
                  ->where('hospital_name', $request->hospital_name)
                  ->first();  // نستخدم first للحصول على أول نتيجة

    if (!$hospital) {
        return response()->json(['error' => 'اسم المستشفى غير موجود.'], 404);
    }

    // جلب hospital_id من المستشفى
    $hospital_id = $hospital->hospital_id;

    // التحقق إذا كان الطبيب موجود في جدول hospital_doctors مع المستشفى
    $doctorInHospital = DB::table('hospital_doctors')
                          ->where('doctor_id', auth()->user()->doctor_id)
                          ->where('hospital_id', $hospital_id)
                          ->exists();

    if (!$doctorInHospital) {
        return response()->json(['error' => 'عذرا، لا يمكنك إضافة مواعيد حتى يتم إضافتك من قبل المستشفى.'], 403);
    }

    // التحقق من صحة البيانات المدخلة
    $request->validate([
        'hospital_name' => 'required|string',  // التأكد من أن اسم المستشفى مدخل
        'day_of_week' => 'required|string',
        'start_time' => 'required',
        'end_time' => 'required',
        
    ]);

    // إضافة الموعد
    $schedule = Schedule::create([
        'doctor_id' => auth()->user()->doctor_id,
        'hospital_id' => $hospital_id,  // استخدام hospital_id المسترجع
        'day_of_week' => $request->day_of_week,
        'start_time' => Carbon::parse($request->start_time)->format('H:i'),
        'end_time' => Carbon::parse($request->end_time)->format('H:i'),
        'status' => 'متاح',
    ]);

    // إرجاع الاستجابة مع اسم المستشفى
    return response()->json([
        'message' => 'تم إضافة الموعد بنجاح',
        'schedule' => $schedule,
        'hospital_name' => $hospital->hospital_name  // إضافة اسم المستشفى في الاستجابة
    ]);
}


// 🔹 تعديل موعد
public function update(Request $request, $id)
{
    $schedule = Schedule::where('schedule_id', $id)->firstOrFail();

    if ($schedule->doctor_id !== auth()->user()->doctor_id) {
        return response()->json(['error' => 'ليس لديك إذن لتعديل هذا الموعد'], 403);
    }

    Log::info('طلب تعديل موعد:', $request->all());

    // حفظ البيانات القديمة قبل التحديث
    $oldStartTime = $schedule->start_time;
    $oldEndTime = $schedule->end_time;

    // تحديث القيم المقترحة
    $schedule->update([
        'proposed_start_time' => $request->start_time,
        'proposed_end_time' => $request->end_time,
        'status' => 'متاح',
    ]);

    Log::info('تم تحديث القيم المقترحة', [
        'proposed_start_time' => $schedule->proposed_start_time,
        'proposed_end_time' => $schedule->proposed_end_time
    ]);

    // 🔹 جلب user_id الخاص بالمستشفى
    $hospitalUserId = Hospital::where('hospital_id', $schedule->hospital_id)->value('user_id');

    if (!$hospitalUserId) {
        Log::error('لم يتم العثور على user_id للمستشفى', ['hospital_id' => $schedule->hospital_id]);
        return response()->json(['error' => 'لم يتم العثور على المستخدم المسؤول عن المستشفى'], 500);
    }

    // 🔹 إنشاء معرف الطلب (request_id) في جدول الإشعارات
    $requestId = DB::table('notifications')->insertGetId([
        'user_id' => $hospitalUserId,
        'title' => 'طلب تعديل موعد',
        'message' => 'تم طلب تعديل موعد من قبل الطبيب ' . auth()->user()->name . 
                    ' من ' . $oldStartTime . ' - ' . $oldEndTime . 
                    ' إلى ' . $request->start_time . ' - ' . $request->end_time . 
                    '، يرجى الموافقة أو الرفض.',
        'type' => 'editing',
        'is_read' => 0,
        'created_at' => Carbon::now(),
    ]);

    // تحديث الإشعار بإضافة معرف الطلب (request_id)
    DB::table('notifications')->where('notification_id', $requestId)->update([
       'request_id' => $schedule->schedule_id,

    ]);

    return response()->json(['message' => 'تم تحديث الموعد، في انتظار موافقة المستشفى']);
}
public function reviewSchedule($notificationId)
{
    // جلب الإشعار
    $notification = DB::table('notifications')->where('notification_id', $notificationId)->first();

    if (!$notification) {
        return response()->json(['message' => 'الإشعار غير موجود'], 404);
    }

    // جلب الموعد المرتبط
    $schedule = Schedule::where('schedule_id', $notification->request_id)->first();

    if (!$schedule) {
        return response()->json(['message' => 'الموعد غير موجود'], 404);
    }

    // تعيين الحالة (مثلاً دائماً approval)
    $status = 'approved'; // أو 'rejected' حسب ما تريد

    if ($status === 'approved') {
        $schedule->update([
            'start_time' => $schedule->proposed_start_time,
            'end_time' => $schedule->proposed_end_time,
            'status' => 'متاح',
            'proposed_start_time' => null,
            'proposed_end_time' => null
        ]);
        $message = 'تمت الموافقة على تعديل الموعد وأصبح متاحًا.';
    } else {
        $schedule->update(['status' => 'rejected']);
        $message = 'تم رفض تعديل الموعد.';
    }

    // إشعار للطبيب
    DB::table('notifications')->insert([
        'user_id' => User::where('doctor_id', $schedule->doctor_id)->value('user_id'),
        'title' => $status === 'approved' ? 'تمت الموافقة على التعديل' : 'تم رفض تعديل الموعد',
        'message' => $message,
        'type' => 'booking',
        'is_read' => 0,
        'created_at' => now()
    ]);

    return response()->json(['message' => $message]);
}

//رفض تعديل الموعد 
public function rejectScheduleEdit($notificationId)
{
    // جلب الإشعار
    $notification = DB::table('notifications')->where('notification_id', $notificationId)->first();

    if (!$notification) {
        return response()->json(['message' => 'الإشعار غير موجود'], 404);
    }

    // جلب الموعد المرتبط
    $schedule = Schedule::where('schedule_id', $notification->request_id)->first();

    if (!$schedule) {
        return response()->json(['message' => 'الموعد غير موجود'], 404);
    }

    // تحديث حالة الموعد إلى "مرفوض" وإزالة الأوقات المقترحة
    $schedule->update([
        'proposed_start_time' => null,
        'proposed_end_time' => null,
        'status' => 'متاح'
    ]);

    // إشعار للطبيب برفض التعديل
    DB::table('notifications')->insert([
        'user_id' => User::where('doctor_id', $schedule->doctor_id)->value('user_id'),
        'title' => 'تم رفض تعديل الموعد',
        'message' => 'تم رفض التعديل المقترح للموعد من قبل المستشفى.',
        'type' => 'editing',
        'is_read' => 0,
        'created_at' => now()
    ]);

    return response()->json(['message' => 'تم رفض تعديل الموعد بنجاح.']);
}

    // 🔹 حذف موعد
    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        
        if ($schedule->doctor_id !== auth()->user()->doctor_id) {
            return response()->json(['error' => 'ليس لديك إذن لحذف هذا الموعد'], 403);
        }
        
        $schedule->delete();
        return response()->json(['message' => 'تم حذف الموعد بنجاح']);
    }


  
    public function getDoctorHospitals(Request $request)
    {
        $doctorId = auth()->user()->doctor_id;
        Log::info('Doctor ID: ' . $doctorId); // تحقق من القيمة
        
        $hospitals = DB::table('hospital_doctors')
            ->join('hospitals', 'hospital_doctors.hospital_id', '=', 'hospitals.hospital_id')
            ->where('hospital_doctors.doctor_id', $doctorId)
            ->select('hospitals.hospital_name')
            ->get();
    
        return response()->json($hospitals);
    }
    
    


    // 🔹 عرض مواعيد الطبيب بناءً على اختيار المريض
public function showDoctorSchedules($doctorId)
{
    // جلب مواعيد الطبيب بناءً على doctor_id
    $schedules = Schedule::where('doctor_id', $doctorId)
        ->with([
            'doctor' => function ($query) {
                $query->select('doctor_id', 'doctor_name');
            },
            'hospital' => function ($query) {
                $query->select('hospital_id', 'hospital_name');
            }
        ])
        ->get()
        ->map(function ($schedule) {
            return [
                'schedule_id' => $schedule->schedule_id,
                'doctor_name' => $schedule->doctor->doctor_name ?? 'غير معروف',
                'hospital_name' => $schedule->hospital->hospital_name ?? 'غير معروف',
                'day_of_week' => $schedule->day_of_week,
                'start_time' => Carbon::parse($schedule->start_time)->format('h:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('h:i A'),
                'status' => $schedule->status,
                'created_at' => Carbon::parse($schedule->created_at)->format('d-m-Y h:i A'),
                'updated_at' => Carbon::parse($schedule->updated_at)->format('d-m-Y h:i A'),
            ];
        });

    // إذا لم يكن هناك مواعيد
    if ($schedules->isEmpty()) {
        return response()->json(['message' => 'لا يوجد مواعيد لهذا الطبيب'], 404);
    }

    return response()->json($schedules);
}

}
