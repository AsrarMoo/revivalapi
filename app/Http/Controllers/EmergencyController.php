<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Patient;
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
               // $notification->message = "يوجد طلب إسعاف بالقرب من مستشفاكم في الإحداثيات: ($latitude, $longitude)";
                $notification->type = 'ambulance';
                $notification->is_read = 0;

                if ($notification->save()) {
                    $notificationsSent++;

                    // تجهيز بيانات الإشعار مع أزرار القبول والرفض
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
        // التحقق من أن الإشعار يخص هذا المستشفى فقط
        $notification = Notification::where('notification_id', $notificationId)
            ->where('user_id', auth()->id()) // التأكد أن الإشعار يخص المستشفى الحالي
            ->first();
    
        if (!$notification) {
            return response()->json(['message' => 'الإشعار غير موجود أو لا يخص هذا المستخدم.'], 404);
        }
    
        Log::info("محاولة القبول من المستخدم: " . Auth::user()->id);
        Log::info("المعرف المسجل بالإشعار: " . $notification->user_id);
        Log::info("الإشعار ID: $notificationId");
        Log::info("المريض ID من الإشعار: {$notification->created_by}");
        Log::info("المستشفى ID من الإشعار: {$notification->user_id}");
        Log::info("اسم الموقع من الإشعار: {$notification->location_name}");
    
        // تأكيد أن نوع الإشعار هو "ambulance"
        if ($notification->type !== 'ambulance') {
            return response()->json(['message' => 'الإشعار غير صحيح.'], 400);
        }
    
        // التحقق من وجود المستشفى باستخدام user_id المرتبط
        $hospital = Hospital::where('user_id', $notification->user_id)->first();
        if (!$hospital) {
            Log::error("المستشفى غير موجود بمعرف المستخدم: {$notification->user_id}");
            return response()->json(['message' => 'المستشفى غير موجود.'], 400);
        }
    
        // التحقق من وجود المريض
        $patient = Patient::find($notification->created_by);
        if (!$patient) {
            Log::error("المريض غير موجود بمعرف: {$notification->created_by}");
            return response()->json(['message' => 'المريض غير موجود.'], 400);
        }
    
        // التأكد من أن المستشفى له حساب مستخدم
        if (is_null($hospital->user_id)) {
            Log::error("معرف مستخدم المستشفى فارغ");
            return response()->json(['message' => 'معرف المستشفى غير صحيح.'], 400);
        }
    
        // التحقق من أن المريض لم يتم إنقاذه مسبقاً
        $alreadyRescued = AmbulanceRescue::where('patient_id', $notification->created_by)->exists();
        if ($alreadyRescued) {
            return response()->json(['message' => 'تمت الاستجابة لهذا الطلب من مستشفى آخر.'], 403);
        }
    
        // تخزين بيانات الإسعاف
        $ambulanceRescue = new AmbulanceRescue();
        $ambulanceRescue->patient_id = $notification->created_by;    
        $ambulanceRescue->hospital_id = $hospital->hospital_id; // ✅ الصح
        $ambulanceRescue->rescued_by_name = Auth::user()->name;
    
        // استخراج الموقع من الرسالة إن وجد
        $message = $notification->message;
        $locationName = '';
        if (strpos($message, 'الموقع:') !== false) {
            $parts = explode('الموقع:', $message);
            if (isset($parts[1])) {
                $locationName = trim($parts[1]);
            }
        }
    
        $ambulanceRescue->location_name = $locationName;
        $ambulanceRescue->save();
    
        // إرسال إشعار للمريض بأن الإسعاف في الطريق
        $responseNotification = new Notification();
        $responseNotification->user_id = $notification->created_by; // هذا هو المريض
        $responseNotification->created_by = $notification->user_id; // هذا المستشفى اللي وافق
        $responseNotification->title = '🚑 الإسعاف في الطريق إليك';
        $responseNotification->message = "تم قبول طلبك للإسعاف من مستشفى {$hospital->hospital_name}. الإسعاف الآن في الطريق إليك.";
        $responseNotification->type = 'ambulance-response';
        $responseNotification->is_read = 0;
        $responseNotification->save();
    
        // تحديث باقي إشعارات الإسعاف لتصبح "ignored"
        Notification::where('type', 'ambulance')
            ->where('created_by', $notification->created_by)
            ->where('notification_id', '!=', $notification->notification_id)
            ->update(['type' => 'ambulance-ignored']);
            // جلب باقي المستشفيات اللي أرسل لهم إشعار ولم يوافقوا
$otherNotifications = Notification::where('type', 'ambulance-ignored')
->where('created_by', $notification->created_by)
->get();

// إرسال إشعار لكل مستشفى لم توافق
foreach ($otherNotifications as $notif) {
$otherHospital = Hospital::where('user_id', $notif->user_id)->first();
if ($otherHospital) {
    $infoNotification = new Notification();
    $infoNotification->user_id = $notif->user_id;        // المستشفى
    $infoNotification->created_by = $notification->created_by; // المريض
    $infoNotification->title = '🚑 تم إسعاف المريض';
    $infoNotification->message = "تم إسعاف المريض من قبل مستشفى {$hospital->hospital_name}.";
    $infoNotification->type = 'ambulance-response';
    $infoNotification->is_read = 0;
    $infoNotification->save();
}
}

    
        Log::info("تم حفظ طلب الإسعاف بنجاح. المريض: {$notification->created_by}، المستشفى: {$notification->user_id}");
    
        return response()->json(['message' => 'تم قبول طلب الإسعاف بنجاح'], 200);
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
}    