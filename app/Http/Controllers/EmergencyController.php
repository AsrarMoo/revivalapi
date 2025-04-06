<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\Notification;
use Illuminate\Http\Request;

class EmergencyController extends Controller
{
    public function sendEmergencyRequest(Request $request)
    {
        // الحصول على الإحداثيات من الطلب
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        // التحقق إذا كانت الإحداثيات صحيحة
        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'الإحداثيات غير صالحة'], 400);
        }

        // منطق إيجاد أقرب مستشفى بناءً على الإحداثيات
        $hospital = $this->findNearestHospital($latitude, $longitude);

        if ($hospital) {
            // إنشاء إشعار للمستشفى
            $notification = new Notification();
            $notification->user_id = $hospital->id;  // ID المستشفى
            $notification->message = "طلب إسعاف جديد بالقرب منك!";
            $notification->type = "طلب إسعاف";
            $notification->save();

            // إرسال استجابة ناجحة
            return response()->json(['message' => 'تم إرسال الطلب بنجاح'], 200);
        } else {
            // إذا لم يتم العثور على مستشفى
            return response()->json(['message' => 'لم يتم العثور على مستشفى قريب'], 404);
        }
    }

    // دالة إيجاد أقرب مستشفى بناءً على الإحداثيات
    private function findNearestHospital($latitude, $longitude)
    {
        // استخدام الخوارزمية المناسبة لإيجاد أقرب مستشفى (يمكنك استخدام Haversine Formula أو أي خوارزمية أخرى)
        // هنا نستخدم خوارزمية بسيطة لتحديد المستشفى الأقرب بناءً على الإحداثيات.
        
        $hospitals = Hospital::all(); // استرجاع جميع المستشفيات (يمكنك تحسين الاستعلام)

        $nearestHospital = null;
        $minDistance = PHP_INT_MAX;

        foreach ($hospitals as $hospital) {
            $distance = $this->calculateDistance($latitude, $longitude, $hospital->latitude, $hospital->longitude);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestHospital = $hospital;
            }
        }

        return $nearestHospital;
    }

    // دالة لحساب المسافة بين إحداثيين باستخدام Haversine Formula
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // نصف قطر الأرض بالكيلومترات

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDiff = $latTo - $latFrom;
        $lonDiff = $lonTo - $lonFrom;

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDiff / 2) * sin($lonDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // المسافة بالكيلومترات
    }
}
