<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    // 🔹 جلب جميع المواعيد للطبيب المسجل حاليًا
    public function index(Request $request)
    {
        $doctorId = auth()->user()->doctor_id;
    
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
                  //  'proposed_start_time' => $schedule->proposed_start_time,
                    //'proposed_end_time' => $schedule->proposed_end_time,
                    'status' => $schedule->status,
                    'created_at' => $schedule->created_at,
                    'updated_at' => $schedule->updated_at,
                ];
            });
    
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
        'created_at' => $schedule->created_at,
        'updated_at' => $schedule->updated_at,
    ]);
}

    // 🔹 إضافة موعد جديد
    public function store(Request $request)
    {
        $request->validate([
            'hospital_id' => 'required|exists:hospitals,hospital_id',
            'day_of_week' => 'required|string',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        $schedule = Schedule::create([
            'doctor_id' => auth()->user()->doctor_id,
            'hospital_id' => $request->hospital_id,
            'day_of_week' => $request->day_of_week,
            'start_time' => Carbon::parse($request->start_time)->format('H:i'),
            'end_time' => Carbon::parse($request->end_time)->format('H:i'),

            'status' => 'available',
        ]);

        return response()->json(['message' => 'تم إضافة الموعد بنجاح', 'schedule' => $schedule]);
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
            'status' => 'pending',
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

        // 🔹 إرسال إشعار للمستشفى
        DB::table('notifications')->insert([
            'user_id' => $hospitalUserId,
            'title' => 'طلب تعديل موعد',
            'message' => 'تم طلب تعديل موعد من قبل الطبيب ' . auth()->user()->name . 
                        ' من ' . $oldStartTime . ' - ' . $oldEndTime . 
                        ' إلى ' . $request->start_time . ' - ' . $request->end_time . 
                        '، يرجى الموافقة أو الرفض.',
            'type' => 'booking',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'تم تحديث الموعد، في انتظار موافقة المستشفى']);
    }

    // 🔹 مراجعة الموعد من قبل المستشفى (قبول أو رفض)
    public function reviewSchedule(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $schedule = Schedule::where('schedule_id', $id)->firstOrFail();

        if ($request->status === 'approved') {
            // تحديث الجدول إذا تمت الموافقة
            $schedule->update([
                'start_time' => $schedule->proposed_start_time,
                'end_time' => $schedule->proposed_end_time,
                'status' => 'available', 
                'proposed_start_time' => null,
                'proposed_end_time' => null
            ]);
            $message = 'تمت الموافقة على تعديل الموعد وأصبح متاحًا.';
        } else {
            // فقط تحديث الحالة إذا تم الرفض
            $schedule->update(['status' => 'rejected']);
            $message = 'تم رفض تعديل الموعد.';
        }

        // 🔹 إرسال إشعار للطبيب
        DB::table('notifications')->insert([
            'user_id' => User::where('doctor_id', $schedule->doctor_id)->value('user_id'),
            'title' => $request->status === 'approved' ? 'تمت الموافقة على التعديل' : 'تم رفض تعديل الموعد',
            'message' => $message,
            'type' => 'booking',
            'is_read' => 0,
            'created_at' => now()
        ]);

        return response()->json(['message' => $message]);
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
    
    
}
