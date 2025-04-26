<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HospitalDoctorRequest;
use App\Models\HospitalDoctor;
use App\Models\Notification;

class HospitalDoctorRequestApprovalController extends Controller
{
    // قبول أو رفض الطلب من قبل وزارة الصحة
    public function updateDoctorRequestStatus(Request $request, $request_id, $action)
    {
        $user = Auth::user();
    
        if (!$user || $user->user_type !== 'healthMinistry') {
            return response()->json(['error' => 'غير مصرح لك بإدارة الطلبات.'], 403);
        }
    
        $doctorRequest = HospitalDoctorRequest::where('request_id', $request_id)->first();
    
        if (!$doctorRequest) {
            return response()->json(['error' => 'الطلب غير موجود.'], 404);
        }
    
        if ($doctorRequest->status !== 'معلق') {
            return response()->json(['error' => 'تمت معالجة هذا الطلب بالفعل.'], 400);
        }
    
        // تحويل نوع العملية إلى status
        if ($action === 'accept') {
            $status = 'مقبول';
        } elseif ($action === 'reject') {
            $status = 'مرفوض';
        } else {
            return response()->json(['error' => 'نوع العملية غير معروف.'], 400);
        }
    
        $doctorRequest->status = $status;
        $doctorRequest->save();

        // إرسال إشعار للطبيب والمستشفى
        $message = ($status === 'مقبول')
            ? 'تمت الموافقة على طلب إضافة الطبيب بنجاح.'
            : 'تم رفض طلب إضافة الطبيب.';

        try {
            // جلب معرف المستخدم للمستشفى والطبيب
            $hospitalUserId = $doctorRequest->hospital->user_id ?? null;
            $doctorUserId = $doctorRequest->doctor->user_id ?? null;

            if ($hospitalUserId) {
                Notification::create([
                    'user_id' => $hospitalUserId,
                    'created_by' => $user->user_id,
                    'title' => 'تحديث حالة طلب إضافة طبيب',
                    'message' => $message,
                    'type' => 'general',
                ]);
            }

            if ($doctorUserId) {
                Notification::create([
                    'user_id' => $doctorUserId,
                    'created_by' => $user->user_id,
                    'title' => 'تحديث حالة طلب إضافة طبيب',
                    'message' => $message,
                    'type' => 'general',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء إرسال الإشعارات.', 'details' => $e->getMessage()], 500);
        }

        // إذا تمت الموافقة، يتم إضافة الطبيب للمستشفى
        if ($status === 'مقبول') {
            try {
                // التحقق من أن بيانات الطبيب والمستشفى غير فارغة
                if (empty($doctorRequest->doctor_id) || empty($doctorRequest->hospital_id)) {
                    return response()->json([
                        'error' => 'بيانات الطبيب أو المستشفى غير مكتملة.'
                    ], 400);
                }
        
                // التحقق مما إذا كان الطبيب مضافًا مسبقًا في المستشفى
                $existingRecord = HospitalDoctor::where('doctor_id', $doctorRequest->doctor_id)
                                                ->where('hospital_id', $doctorRequest->hospital_id)
                                                ->exists();
        
                if ($existingRecord) {
                    return response()->json([
                        'error' => 'الطبيب مضاف بالفعل لهذا المستشفى.'
                    ], 400);
                }
        
                // إضافة الطبيب إلى المستشفى
                $hospitalDoctor = HospitalDoctor::create([
                    'doctor_id' => $doctorRequest->doctor_id,
                    'hospital_id' => $doctorRequest->hospital_id,
                    'assigned_at' => now(),
                ]);
        
                return response()->json([
                    'message' => 'تمت الموافقة على الطلب وإضافة الطبيب بنجاح.',
                    'hospital_doctor' => [
                        'doctor_name' => $doctorRequest->doctor->doctor_name ?? 'غير معروف',
                        'hospital_name' => $doctorRequest->hospital->hospital_name ?? 'غير معروف',
                        'assigned_at' => now(),
                    ]
                ], 200);
        
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'حدث خطأ أثناء إضافة الطبيب إلى المستشفى.',
                    'details' => $e->getMessage()
                ], 500);
            }
        }
        
        return response()->json([
            'message' => 'تم تحديث الطلب بنجاح.',
            'status' => $doctorRequest->status
        ], 200);
    }

    // جلب جميع الطلبات المعلقة
    public function pendingRequests()
    {
        $pendingRequests = HospitalDoctorRequest::where('status', 'pending')->get();

        if ($pendingRequests->isEmpty()) {
            return response()->json(['message' => 'لا توجد طلبات معلقة.'], 200);
        }

        return response()->json($pendingRequests, 200);
    }
}
