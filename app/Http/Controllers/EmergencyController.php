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
                        'created_at' => $notification->created_at,
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
    // الحصول على الإشعار بناءً على ID
    $notification = Notification::findOrFail($notificationId);
    
    // تأكيد أن نوع الإشعار هو "ambulance"
    if ($notification->type !== 'ambulance') {
        return response()->json(['message' => 'الإشعار غير صحيح.'], 400);
    }

    Log::info("بحث الإشعار باستخدام ID: " . $notificationId);
    Log::info("معرف المستشفى من الإشعار: " . $notification->user_id);
    Log::info("معرف المريض من الإشعار: " . $notification->created_by);
    
    // التحقق من المستشفى الذي وافق على الإسعاف (استخدام معرف المستشفى من الإشعار)
    $hospital = Hospital::find($notification->user_id);
    
    // إضافة لوج للتأكد من أن المستشفى تم استرجاعه بشكل صحيح
    if (!$hospital) {
        Log::error("لم يتم العثور على المستشفى بمعرف " . $notification->user_id);
        return response()->json(['message' => 'المستشفى غير موجود.'], 400);
    }
    
    Log::info("المستشفى الذي وافق على الإسعاف: " . $notification->user_id);

    // الحصول على بيانات المريض باستخدام معرف المريض من الإشعار
    $patient = Patient::find($notification->created_by);
    Log::info("معرف المريض من الإشعار: " . $notification->created_by);

    if (!$patient) {
        Log::error("معرف المريض فارغ. الإشعار ID: " . $notification->created_by);
        return response()->json(['message' => 'المريض غير موجود.'], 400);
    }


    // تأكيد أن hospital_id موجود وأنه ليس null
    if (is_null($hospital->user_id)) {
        Log::error("معرف المستشفى فارغ. الإشعار ID: " . $notification->use_id);
        return response()->json(['message' => 'معرف المستشفى غير صحيح.'], 400);
    }

    // تخزين السجل في جدول الإسعاف (استخدام معرف المستشفى من الإشعار)
    Log::info("تخزين سجل الإسعاف للمريض ID: " . $patient->created_by . " المستشفى ID: " . $hospital->usr_id);
    $ambulanceRescue = new AmbulanceRescue();
    $ambulanceRescue->patient_id = $notification->created_by;  // استخدام معرف المريض من الإشعار
    $ambulanceRescue->hospital_id = $notification->user_id;  // استخدام معرف المستشفى من الإشعار
    $ambulanceRescue->rescued_by_name = Auth::user()->name;  // اسم الشخص الذي قام بالإسعاف
    $ambulanceRescue->latitude = $notification->latitude;  // يمكن استبدالها بالإحداثيات الحقيقية
    $ambulanceRescue->longitude = $notification->longitude;
    $ambulanceRescue->save();

    Log::info("تم تخزين سجل الإسعاف بنجاح للمريض ID: " . $patient->id);

    // تحديث حالة الإشعار للمستشفى
    $notification->is_read = 1; // يمكن تغييرها إلى حالة مقبول
    $notification->message = 'تم إسعاف المريض بنجاح';
    $notification->save();

    Log::info("تم تحديث حالة الإشعار بنجاح للمستشفى ID: " . $hospital->id);

    return response()->json(['message' => 'تم قبول طلب الإسعاف بنجاح.']);


        // إرسال إشعار للمستشفيات الأخرى
        $this->sendNotificationToOtherHospitals($hospital, $notification);
    
        return response()->json([
            'message' => 'تم قبول طلب الإسعاف بنجاح.',
            'ambulance_rescue' => $ambulanceRescue,
        ]);
    }
    
    public function rejectAmbulanceRequest($notificationId)
    {
        // الحصول على الإشعار بناءً على ID
        $notification = Notification::findOrFail($notificationId);
        
        // تأكيد أن نوع الإشعار هو "ambulance"
        if ($notification->type !== 'ambulance') {
            return response()->json(['message' => 'الإشعار غير صحيح.'], 400);
        }
    
        // تحديث حالة الإشعار للمستشفى
        $notification->is_read = 1;
        $notification->message = 'تم رفض الإسعاف من قبلكم';
        $notification->save();
    
        // إرسال إشعار للمستشفى الآخر بأنه تم رفض الإسعاف
        $this->sendNotificationToOtherHospitals(Hospital::find($notification->user_id), $notification);
    
        return response()->json([
            'message' => 'تم رفض طلب الإسعاف بنجاح.',
        ]);
    }
    
    public function sendNotificationToOtherHospitals($hospital, $notification)
    {
        // إرسال إشعار للمستشفيات الأخرى
        $otherHospitals = Hospital::where('hospital_id', '!=', $hospital->hospital_id)->get();
        foreach ($otherHospitals as $otherHospital) {
            // جلب المستخدم المرتبط بالمستشفى
            $hospitalUserId = $otherHospital->user_id;
    
            // استخدام إشعار جديد
            $newNotification = new Notification();
            $newNotification->user_id = $hospitalUserId;
            $newNotification->created_by = $notification->created_by;
            $newNotification->title = 'طلب إسعاف تم استكماله';
            $newNotification->message = "تم إسعاف المريض من مستشفى {$hospital->hospital_name}.";
            $newNotification->type = 'ambulance';
            $newNotification->is_read = 0;
            $newNotification->save();
        }
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