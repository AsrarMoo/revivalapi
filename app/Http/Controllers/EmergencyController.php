<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Patient;
use App\Models\User;
use App\Models\AmbulanceRescue;
use Carbon\Carbon;


class EmergencyController extends Controller
{
    function getAddressFromCoordinates($lat, $lon)
    {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon";
    
        $opts = [
            "http" => [
                "header" => "User-Agent: LaravelRevivalApp/1.0"
            ]
        ];
    
        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);
    
        $data = json_decode($response);
    
        if (isset($data->display_name)) {
            return $data->display_name;
        } else {
            return "إحداثيات: ($lat, $lon)";
        }
    }
    

  public function findNearestHospitals(Request $request)
{
    $userId = Auth::id();

    if (!$userId) {
        return response()->json([
            'message' => 'لم يتم العثور على مستخدم مسجل دخول',
        ], 401);
    }

    // تحقق إذا كان المستخدم محظورًا
    $user = User::find($userId);
    if ($user->is_banned) {
        return response()->json([
            'message' => 'أنت محظور من إرسال طلبات الإسعاف.',
        ], 403);
    }

    // تحقق إذا أرسل المستخدم طلب إسعاف بالفعل اليوم
    $sentToday = Notification::where('created_by', $userId)
        ->where('type', 'ambulance')
        ->whereDate('created_at', Carbon::today())
        ->exists();

    if ($sentToday) {
        return response()->json([
            'message' => 'لقد قمت بإرسال طلب إسعاف اليوم بالفعل. يرجى الانتظار حتى الغد.',
        ], 429); // 429 Too Many Requests
    }

    $latitude = $request->input('latitude');
    $longitude = $request->input('longitude');

    Log::debug("Received Latitude: $latitude, Longitude: $longitude");

    try {
        $hospitals = DB::table('hospitals')
            ->select('hospital_id', 'hospital_name', 'latitude', 'longitude', DB::raw('
                (6371 * acos(cos(radians(?)) * cos(radians(hospitals.latitude)) * cos(radians(hospitals.longitude) - radians(?)) + sin(radians(?)) * sin(radians(hospitals.latitude)))) AS distance
            '))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByRaw('distance ASC')
            ->limit(3)
            ->setBindings([$latitude, $longitude, $latitude])
            ->get();

        Log::debug('Found Hospitals: ', $hospitals->toArray());
    } catch (\Exception $e) {
        Log::error('Error while retrieving hospitals: ' . $e->getMessage());
        return response()->json(['error' => 'فشل في جلب المستشفيات'], 500);
    }

    $notificationsSent = 0;
    $responseNotifications = [];

    foreach ($hospitals as $hospital) {
        try {
            Log::debug("Sending notification to hospital ID: {$hospital->hospital_id}, Name: {$hospital->hospital_name}");

            $hospitalModel = Hospital::find($hospital->hospital_id);
            $hospitalUserId = $hospitalModel?->user_id;

            if (!$hospitalUserId) {
                Log::warning("No user_id found for hospital ID: {$hospital->hospital_id}");
                continue;
            }

            $notification = new Notification();
            $notification->user_id = $hospitalUserId;
            $notification->created_by = $userId;
            $notification->title = 'طلب إسعاف قريب';
            $locationName = $this->getAddressFromCoordinates($latitude, $longitude);
            $notification->message = "يوجد طلب إسعاف بالقرب من مستشفاكم في الموقع: $locationName";
            $notification->type = 'ambulance';
            $notification->is_read = 0;

            if ($notification->save()) {
                $notificationsSent++;

                $responseNotifications[] = [
                    'notification_id' => $notification->notification_id,
                    'user_id' => $notification->user_id,
                    'created_by' => $notification->created_by,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'is_read' => $notification->is_read,
                    'created_at' => Carbon::parse($notification->created_at)->format('Y-m-d h:i A'),
                    'action_required' => true,
                    'actions' => [
                        'accept_url' => "/api/ambulance-request/{$notification->notification_id}/accept",
                        'reject_url' => "/api/ambulance-request/{$notification->notification_id}/reject",
                    ],
                ];

                Log::debug("Notification sent to hospital ID: {$hospital->hospital_id}");
            } else {
                Log::error("Failed to send notification to hospital ID: {$hospital->hospital_id}");
            }
        } catch (\Exception $e) {
            Log::error("Error sending notification to hospital ID: {$hospital->hospital_id}, Error: " . $e->getMessage());
        }
    }

    Log::debug('Notifications sent: ' . $notificationsSent);

    return response()->json([
        'message' => $notificationsSent > 0 ? "$notificationsSent إشعار تم إرساله بنجاح." : 'لم يتم إرسال أي إشعارات.',
        'hospitals' => $hospitals,
        'notifications' => $responseNotifications
    ]);
}

public function acceptAmbulanceRequest($notificationId)
{
    $notification = Notification::where('notification_id', $notificationId)
        ->where('user_id', auth()->id())
        ->first();

    if (!$notification) {
        return response()->json(['message' => 'الإشعار غير موجود أو لا يخص هذا المستخدم.'], 404);
    }

    if ($notification->type === 'ambulance-ignored') {
        return response()->json(['message' => 'تم إسعاف المريض من قبل مستشفى آخر في نفس اليوم.'], 403);
    }

    if ($notification->type !== 'ambulance') {
        return response()->json(['message' => 'الإشعار غير صحيح.'], 400);
    }

    $hospital = Hospital::where('user_id', $notification->user_id)->first();
    if (!$hospital) {
        return response()->json(['message' => 'المستشفى غير موجود.'], 400);
    }

    // جلب المريض بناءً على user_id الموجود في created_by
    $patient = Patient::where('user_id', $notification->created_by)->first();
    if (!$patient) {
        return response()->json(['message' => 'المريض غير موجود.'], 400);
    }

    if (is_null($hospital->user_id)) {
        return response()->json(['message' => 'معرف المستشفى غير صحيح.'], 400);
    }

    $alreadyRescuedToday = AmbulanceRescue::where('patient_id', $patient->patient_id)
        ->whereDate('created_at', Carbon::today())
        ->where('hospital_id', '!=', $hospital->hospital_id)
        ->exists();

    if ($alreadyRescuedToday) {
        return response()->json(['message' => 'تم إسعاف المريض من قبل مستشفى آخر اليوم، لا يمكن قبول الطلب.'], 403);
    }

    // استخراج الموقع من الرسالة
    $message = $notification->message;
    $locationName = '';
    if (strpos($message, 'الموقع:') !== false) {
        $parts = explode('الموقع:', $message);
        if (isset($parts[1])) {
            $locationName = trim($parts[1]);
        }
    }

    // تخزين بيانات الإسعاف
    $ambulanceRescue = new AmbulanceRescue();
    $ambulanceRescue->patient_id = $patient->patient_id; // 🔧 تم التعديل هنا
    $ambulanceRescue->hospital_id = $hospital->hospital_id;
    $ambulanceRescue->user_id = $notification->created_by;
    $ambulanceRescue->location_name = $locationName;
    $ambulanceRescue->save();

    // إشعار للمريض
    $responseNotification = new Notification();
    $responseNotification->user_id = $notification->created_by;
    $responseNotification->created_by = $notification->user_id;
    $responseNotification->title = '🚑 الإسعاف في الطريق إليك';
    $responseNotification->message = "تم قبول طلبك للإسعاف من مستشفى {$hospital->hospital_name}. الإسعاف الآن في الطريق إليك.";
    $responseNotification->type = 'ambulance-response';
    $responseNotification->is_read = 0;
    $responseNotification->save();

    Notification::where('type', 'ambulance')
        ->where('created_by', $notification->created_by)
        ->where('notification_id', '!=', $notification->notification_id)
        ->update(['type' => 'ambulance-ignored']);

    return response()->json(['message' => 'تم قبول طلب الإسعاف بنجاح'], 200);
}



//رفض طلب الاسعاف 
public function rejectAmbulanceRequest($notificationId)
{
    $notification = Notification::where('notification_id', $notificationId)
        ->where('user_id', auth()->id()) // تأكد أن الإشعار يخص المستشفى الحالي
        ->first();

    if (!$notification) {
        return response()->json(['message' => 'الإشعار غير موجود أو لا يخص هذا المستخدم.'], 404);
    }

    if ($notification->type !== 'ambulance') {
        return response()->json(['message' => 'الإشعار ليس من نوع طلب إسعاف.'], 400);
    }

    // تحديث نوع الإشعار إلى "مرفوض"
    $notification->update(['type' => 'rejected']);

    return response()->json(['message' => 'تم رفض طلب الإسعاف بنجاح.'], 200);
}


    public function showPatientMedicalRecord($ambulanceRescueId)
    {
        // الحصول على سجل الإسعاف
        $ambulanceRescue = AmbulanceRescue::findOrFail($ambulanceRescueId);
        $patient = Patient::findOrFail($ambulanceRescue->patient_id);
    
        // الحصول على السجل الطبي للمريض
        $medicalRecord = $patient->medicalRecord;
    
        return response()->json([
            'patient' => $patient,
            'medical_record' => $medicalRecord,
        ]);
    }


    public function findNearestHospitalsForAnotherPatient(Request $request)
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'message' => 'لم يتم العثور على مستخدم مسجل دخول',
            ], 401);
        }
    
        // تحقق إذا كان المستخدم محظورًا
        $user = User::find($userId);
        if ($user->is_banned) {
            return response()->json([
                'message' => 'أنت محظور من إرسال طلبات الإسعاف.',
            ], 403);
        }
    
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $patientName = $request->input('patient_name'); // اسم المريض
        
        $patient = null;
    
        // إذا تم إدخال اسم المريض، نحاول العثور عليه في قاعدة البيانات
        if ($patientName) {
            $patient = Patient::where('patient_name', $patientName)->first(); // البحث عن المريض
    
            // إذا لم يتم العثور على المريض
            if (!$patient) {
                return response()->json([
                    'message' => 'المريض غير موجود في السجلات.',
                ], 404);
            }
        }
    
        $patientId = $patient ? $patient->patient_id : null; // إذا تم العثور على المريض، جلب معرفه
    
        Log::debug("Received Latitude: $latitude, Longitude: $longitude for patient: $patientName");
    
        try {
            // جلب المستشفيات القريبة
            $hospitals = DB::table('hospitals')
                ->select('hospital_id', 'hospital_name', 'latitude', 'longitude', DB::raw('
                    (6371 * acos(cos(radians(?)) * cos(radians(hospitals.latitude)) * cos(radians(hospitals.longitude) - radians(?)) + sin(radians(?)) * sin(radians(hospitals.latitude)))) AS distance
                '))
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByRaw('distance ASC')
                ->limit(3)
                ->setBindings([$latitude, $longitude, $latitude])
                ->get();
    
            Log::debug('Found Hospitals: ', $hospitals->toArray());
        } catch (\Exception $e) {
            Log::error('Error while retrieving hospitals: ' . $e->getMessage());
            return response()->json(['error' => 'فشل في جلب المستشفيات'], 500);
        }
    
        $notificationsSent = 0;
        $responseNotifications = [];
    
        // إرسال الإشعارات للمستشفيات
        foreach ($hospitals as $hospital) {
            try {
                Log::debug("Sending notification to hospital ID: {$hospital->hospital_id}, Name: {$hospital->hospital_name}");
    
                $hospitalModel = Hospital::find($hospital->hospital_id);
                $hospitalUserId = $hospitalModel?->user_id;
    
                if (!$hospitalUserId) {
                    Log::warning("No user_id found for hospital ID: {$hospital->hospital_id}");
                    continue;
                }
    
                // إرسال الإشعار للمستشفى
                $notification = new Notification();
                $notification->user_id = $hospitalUserId;
                $notification->created_by = $userId;  // المستخدم الذي طلب الإسعاف
                $notification->title = 'طلب إسعاف لمريض آخر';
                $locationName = $this->getAddressFromCoordinates($latitude, $longitude);
    
                // إذا كان هناك اسم مريض موجود، نضيف السجل الطبي
                if ($patient) {
                    $notification->message = "يوجد طلب إسعاف لمريض آخر ($patientName) بالقرب من مستشفاكم. الموقع: $locationName"; 
                } else {
                    // إذا لم يكن هناك اسم مريض أو المريض غير موجود في السجلات
                    $notification->message = "يوجد طلب إسعاف لمريض آخر (غير مسجل) بالقرب من مستشفاكم. الموقع: $locationName"; 
                }
                $notification->type = 'ambulance';
                $notification->is_read = 0;
    
                if ($notification->save()) {
                    $notificationsSent++;
    
                    // تجهيز بيانات الإشعار مع أزرار القبول والرفض
                    $responseNotifications[] = [
                        'notification_id' => $notification->notification_id,
                        'user_id' => $notification->user_id,
                        'created_by' => $userId,  // الشخص الذي طلب الإسعاف
                        'created_by_name' => Auth::user()->name, // إضافة اسم الشخص الذي أرسل الطلب
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type,
                        'is_read' => $notification->is_read,
                        'created_at' => Carbon::parse($notification->created_at)->format('Y-m-d h:i A'),
                        'action_required' => true,
                        'actions' => [
                            'accept_url' => "/api/ambulance-request/{$notification->notification_id}/accept-for-other",
                            'reject_url' => "/api/ambulance-request/{$notification->notification_id}/reject-for-other",
                        ],
                    ];
    
                    Log::debug("Notification sent to hospital ID: {$hospital->hospital_id}");
                } else {
                    Log::error("Failed to send notification to hospital ID: {$hospital->hospital_id}");
                }
            } catch (\Exception $e) {
                Log::error("Error sending notification to hospital ID: {$hospital->hospital_id}, Error: " . $e->getMessage());
            }
        }
    
        Log::debug('Notifications sent: ' . $notificationsSent);
    
        return response()->json([
            'message' => $notificationsSent > 0 ? "$notificationsSent إشعار تم إرساله بنجاح." : 'لم يتم إرسال أي إشعارات.',
            'hospitals' => $hospitals,
            'notifications' => $responseNotifications
        ]);
    }
   

    public function acceptAmbulanceRequestForOther($notificationId)
    {
        $notification = Notification::where('notification_id', $notificationId)
            ->where('user_id', auth()->id())
            ->first();
    
        if (!$notification) {
            return response()->json(['message' => 'الإشعار غير موجود أو لا يخص هذا المستخدم.'], 404);
        }
    
        if ($notification->type !== 'ambulance') {
            return response()->json(['message' => 'الإشعار غير صحيح.'], 400);
        }
    
        $hospital = Hospital::where('user_id', $notification->user_id)->first();
        if (!$hospital) {
            return response()->json(['message' => 'المستشفى غير موجود.'], 400);
        }
    
        // استخراج اسم المريض من داخل القوسين
        $patientName = null;
        if (preg_match('/\((.*?)\)/u', $notification->message, $matches)) {
            $patientName = trim($matches[1]);
        }
    
        if (!$patientName) {
            return response()->json(['message' => 'لم يتم استخراج اسم المريض من نص الرسالة.'], 400);
        }
    
        // البحث عن المريض بواسطة الاسم
        $patient = Patient::where('patient_name', $patientName)->first();
        if (!$patient) {
            return response()->json(['message' => 'المريض غير موجود في قاعدة البيانات.'], 404);
        }
    
        // التحقق هل تم إرسال طلب إسعاف لهذا المريض اليوم من نفس مرسل الطلب
        $alreadyRescuedToday = AmbulanceRescue::where('patient_id', $patient->patient_id)
            ->where('user_id', $notification->created_by)
            ->whereDate('created_at', Carbon::today())
            ->exists();
    
        if ($alreadyRescuedToday) {
            return response()->json(['message' => 'تمت الاستجابة لهذا الطلب مسبقاً اليوم.'], 403);
        }
    
        // استخراج الموقع من الرسالة
        $message = $notification->message;
        $locationName = '';
        if (strpos($message, 'الموقع:') !== false) {
            $parts = explode('الموقع:', $message);
            if (isset($parts[1])) {
                $locationName = trim($parts[1]);
            }
        }
    
        // حفظ بيانات الإسعاف
        $ambulanceRescue = new AmbulanceRescue();
        $ambulanceRescue->patient_id = $patient->patient_id;
        $ambulanceRescue->hospital_id = $hospital->hospital_id;
        $ambulanceRescue->user_id = $notification->created_by;
        $ambulanceRescue->location_name = $locationName;
        $ambulanceRescue->save();
    
        // إرسال إشعار لمُرسل الطلب
        $responseNotification = new Notification();
        $responseNotification->user_id = $notification->created_by;
        $responseNotification->created_by = auth()->id();
        $responseNotification->title = '🚑 تم الاستجابة لطلب الإسعاف';
        $responseNotification->message = "تم إرسال سيارة إسعاف إلى المريض ($patientName) من مستشفى {$hospital->hospital_name}.";
        $responseNotification->type = 'ambulance-response';
        $responseNotification->is_read = 0;
        $responseNotification->save();
    
        // تجاهل الإشعارات الأخرى لنفس المريض
        Notification::where('type', 'ambulance')
            ->where('message', 'like', "%($patientName)%")
            ->where('notification_id', '!=', $notification->notification_id)
            ->update(['type' => 'ambulance-ignored']);
    
        // إرسال إشعار للمستشفيات الأخرى أن المريض تم إسعافه
        $otherNotifications = Notification::where('type', 'ambulance')
            ->where('message', 'like', "%($patientName)%")
            ->where('notification_id', '!=', $notification->notification_id)
            ->get();
    
        foreach ($otherNotifications as $notif) {
            $otherHospital = Hospital::where('user_id', $notif->user_id)->first();
            if ($otherHospital) {
                $infoNotification = new Notification();
                $infoNotification->user_id = $notif->user_id;
                $infoNotification->created_by = $notification->created_by;
                $infoNotification->title = '🚑 تم إسعاف المريض';
                $infoNotification->message = "تم إسعاف المريض ($patientName) من قبل مستشفى {$hospital->hospital_name}.";
                $infoNotification->type = 'ambulance-response';
                $infoNotification->is_read = 0;
                $infoNotification->save();
            }
        }
    
        return response()->json(['message' => 'تم قبول طلب الإسعاف بنجاح'], 200);
    }
    public function markFakeAmbulanceRequest($id)
    {
        $rescueRequest = AmbulanceRescue::find($id);
    
        if (!$rescueRequest) {
            return response()->json(['message' => 'Ambulance rescue request not found'], 404);
        }
    
        if ($rescueRequest->status !== 'مكتمل') {
            return response()->json(['message' => 'Request is not in a valid state to be marked fake'], 400);
        }
    
        // تغيير حالة الطلب إلى "كاذب"
        $rescueRequest->status = 'كاذب';
        $rescueRequest->save();
    
        // حظر المستخدم إذا موجود
        if ($rescueRequest->user_id) {
            $user = User::find($rescueRequest->user_id);
            if ($user) {
                $user->is_banned = true;
                $user->save();
        // إرسال نفس الإشعار للمريض اللي عمل الطلب
        $patientUser = User::where('patient_id', $rescueRequest->patient_id)->first();
        if ($patientUser) {
            $patientNotification = new Notification();
            $patientNotification->user_id = $patientUser->user_id;
            $patientNotification->created_by = auth()->id(); // أو $hospitalUserId إذا عندك
            $patientNotification->title = '🚨 تم حظرك من طلب الإسعاف';
            $patientNotification->message = 'تم حظرك من إرسال طلبات الإسعاف بسبب بلاغ من المستشفى بأن الطلب كان وهميًا.';
            $patientNotification->type = 'rejected';
            $patientNotification->is_read = 0;
            $patientNotification->save();
        }
    
        return response()->json(['message' => 'تم حظر المستخدم بنجاح بسبب طلب إسعاف وهمي.']);
    }
    
        }
    }}
    