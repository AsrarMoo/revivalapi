<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HospitalDoctorRequest;
use App\Models\Notification;
use App\Models\HospitalDoctor;
use App\Models\Doctor;
use App\Models\Hospital;

class HospitalDoctorRequestApprovalController extends Controller
{
    // قبول أو رفض الطلب من قبل وزارة الصحة باستخدام المعرف فقط
    public function updateDoctorRequestStatus(Request $request, $notification_id, $action)
    {
        \Log::info("تحديث حالة الإشعار: $notification_id مع نوع العملية: $action");

        // جلب الإشعار بناءً على ID
        $notification = Notification::find($notification_id);

        if (!$notification) {
            return response()->json(['error' => 'الإشعار غير موجود.'], 404);
        }

        // استرجاع الطلب بناءً على request_id
        $doctorRequest = HospitalDoctorRequest::where('request_id', $notification->request_id)->first();

        if (!$doctorRequest) {
            return response()->json(['error' => 'الطلب غير موجود.'], 404);
        }

        // تأكد أن المستخدم لديه صلاحية إدارة الطلبات
        $user = Auth::user();
        if (!$user || $user->user_type !== 'healthMinistry') {
            return response()->json(['error' => 'غير مصرح لك بإدارة الطلبات.'], 403);
        }

        // تأكد من أن الحالة "معلق" قبل التحديث
        if ($doctorRequest->status !== 'pending') {
            return response()->json(['error' => 'تمت معالجة هذا الطلب بالفعل.'], 400);
        }

        // التحقق من نوع العملية (قبول أو رفض)
        if (!in_array($action, ['accept', 'reject'])) {
            return response()->json(['error' => 'نوع العملية غير صحيح. يجب أن تكون "accept" أو "reject".'], 400);
        }

        // تحديد الحالة بناءً على نوع العملية
        $status = ($action === 'accept') ? 'accept' : 'rejected';

        // تحديث حالة الطلب
        $doctorRequest->status = $status;
        $doctorRequest->save();

        // جعل الإشعار كمقرؤ
        $notification->is_read = 1;
        $notification->save();

        // إذا كانت الحالة مقبولة، نقوم بإضافة الطبيب إلى المستشفى في جدول hospital_doctors
        if ($status === 'accept') {
            // التأكد من عدم وجود الطبيب في المستشفى بالفعل
            $existingDoctor = HospitalDoctor::where('hospital_id', $doctorRequest->hospital_id)
                ->where('doctor_id', $doctorRequest->doctor_id)
                ->first();

            if (!$existingDoctor) {
                // إضافة الطبيب إلى المستشفى
                HospitalDoctor::create([
                    'hospital_id' => $doctorRequest->hospital_id,
                    'doctor_id' => $doctorRequest->doctor_id,
                    'assigned_at' => now(), // تعيين الوقت الحالي كوقت تعيين الطبيب للمستشفى
                ]);
            }
        }

        // تحديد الرسالة المناسبة
        $message = ($status === 'accept') ? 'تمت الموافقة على طلب إضافة الطبيب بنجاح.' : 'تم رفض طلب إضافة الطبيب.';

        try {
            // جلب المعرف الخاص بالمستشفى والطبيب
            $hospitalUserId = $doctorRequest->hospital->user_id ?? null;
            $doctorUserId = $doctorRequest->doctor->user_id ?? null;

            // إرسال إشعار إلى المستشفى في حال كان الطلب مقبولًا أو مرفوضًا
            if ($hospitalUserId) {
                Notification::create([
                    'user_id' => $hospitalUserId,
                    'created_by' => $user->user_id,
                    'title' => 'تحديث حالة طلب إضافة طبيب',
                    'message' => $message,
                    'type' => 'general',
                ]);
            }

            // إرسال إشعار للطبيب في حال كان الطلب مقبولًا أو مرفوضًا
            if ($doctorUserId) {
                // جلب اسم المستشفى
                $hospitalName = $doctorRequest->hospital->hospital_name ?? 'مستشفى غير معروف';

                Notification::create([
                    'user_id' => $doctorUserId,
                    'created_by' => $user->user_id,
                    'title' => 'تحديث حالة طلب إضافة طبيب',
                    'message' => $status === 'accept' 
                                ? "تمت الموافقة على طلب إضافة الطبيب بنجاح من قبل مستشفى {$hospitalName}." 
                                : "تم رفض طلب إضافة الطبيب من قبل مستشفى {$hospitalName}.",
                    'type' => 'general',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إرسال الإشعارات.', 'details' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'تم تحديث الطلب بنجاح.',
            'status' => $doctorRequest->status
        ], 200);
    }

    // رفض الطلب من قبل الوزارة 
    public function rejectDoctorRequest($notification_id)
    {
        \Log::info("رفض طلب إضافة طبيب بناءً على الإشعار: $notification_id");

        // جلب الإشعار
        $notification = Notification::find($notification_id);

        if (!$notification) {
            return response()->json(['error' => 'الإشعار غير موجود.'], 404);
        }

        // جلب الطلب
        $doctorRequest = HospitalDoctorRequest::where('request_id', $notification->request_id)->first();

        if (!$doctorRequest) {
            return response()->json(['error' => 'الطلب غير موجود.'], 404);
        }

        // التأكد من صلاحية المستخدم
        $user = Auth::user();
        if (!$user || $user->user_type !== 'healthMinistry') {
            return response()->json(['error' => 'غير مصرح لك برفض الطلبات.'], 403);
        }

        // التأكد من أن الطلب لم يُعالج مسبقًا
        if ($doctorRequest->status !== 'pending') {
            return response()->json(['error' => 'تمت معالجة هذا الطلب مسبقًا.'], 400);
        }

        // تحديث حالة الطلب إلى "مرفوض"
        $doctorRequest->status = 'rejected';
        $doctorRequest->save();

        // جعل الإشعار كمقرؤ
        $notification->is_read = 1;
        $notification->save();

        // الرسالة التي ستُرسل
        $message = 'تم رفض طلب إضافة الطبيب من قبل وزارة الصحة.';

        // إرسال إشعارات إلى المستشفى والطبيب
        try {
            $hospitalUserId = $doctorRequest->hospital->user_id ?? null;
            $doctorUserId = $doctorRequest->doctor->user_id ?? null;

            if ($hospitalUserId) {
                Notification::create([
                    'user_id' => $hospitalUserId,
                    'created_by' => $user->user_id,
                    'title' => 'رفض طلب إضافة طبيب',
                    'message' => $message,
                    'type' => 'general',
                ]);
            }

            if ($doctorUserId) {
                Notification::create([
                    'user_id' => $doctorUserId,
                    'created_by' => $user->user_id,
                    'title' => 'رفض طلب إضافة طبيب',
                    'message' => $message,
                    'type' => 'general',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إرسال الإشعارات.', 'details' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'تم رفض الطلب بنجاح.',
            'status' => $doctorRequest->status
        ], 200);
    }

    // جلب جميع الطلبات المعلقة مع تفاصيل الطبيب
    public function pendingRequests()
    {
        // جلب جميع الطلبات المعلقة مع تفاصيل الطبيب والمستشفى
        $pendingRequests = HospitalDoctorRequest::where('status', 'pending')
            ->with(['hospital', 'doctor']) // إحضار المستشفى والطبيب المرتبطين بالطلب
            ->get();

        if ($pendingRequests->isEmpty()) {
            return response()->json(['message' => 'لا توجد طلبات معلقة.'], 200);
        }

        return response()->json($pendingRequests, 200);
    }

    public function getHospitalDoctors()
    {
        $user = Auth::user();

        // تحقق أن المستخدم مستشفى
        if (!$user || $user->user_type !== 'hospital') {
            return response()->json(['error' => 'غير مصرح لك بالوصول لهذه البيانات.'], 403);
        }

        // جلب hospital_id للمستشفى المرتبط بالمستخدم
        $hospitalId = $user->hospital_id;

        if (!$hospitalId) {
            return response()->json(['error' => 'لم يتم العثور على مستشفى مرتبط بهذا المستخدم.'], 404);
        }

        // جلب الأطباء الذين يعملون في هذا المستشفى مع الصورة
        $doctors = Doctor::whereIn('doctor_id', function ($query) use ($hospitalId) {
            $query->select('doctor_id')
                  ->from('hospital_doctors')
                  ->where('hospital_id', $hospitalId);
        })
        ->select('doctor_id', 'doctor_name', 'doctor_image') // إضافة doctor_image هنا
        ->get();

        if ($doctors->isEmpty()) {
            return response()->json(['message' => 'لا يوجد أطباء مرتبطين بهذا المستشفى.'], 200);
        }

        return response()->json(['doctors' => $doctors], 200);
    }
}
