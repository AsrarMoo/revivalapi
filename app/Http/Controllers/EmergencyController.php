<?php
namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB;
class EmergencyController extends Controller
{
    
   

    
    public function findNearestHospitals(Request $request)
    {
        // Logging لتسجيل مدخلات الطلب
        Log::debug('Received Request:', $request->all());
    
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $userId = $request->input('user_id'); // الحصول على user_id من الطلب
    
        Log::debug("Received Latitude: $latitude, Longitude: $longitude, User ID: $userId");
    
        // استعلام للعثور على أقرب 3 مستشفيات باستخدام Haversine Formula
        try {
            $hospitals = DB::table('hospitals')
            ->select('hospitals.hospital_id', 'hospitals.hospital_name', 'hospitals.latitude', 'hospitals.longitude', DB::raw('
                (6371 * acos(cos(radians(?)) * cos(radians(hospitals.latitude)) * cos(radians(hospitals.longitude) - radians(?)) + sin(radians(?)) * sin(radians(hospitals.latitude)))) AS distance
            )', [$latitude, $longitude, $latitude])) // تمرير المعاملات بشكل صحيح
            ->orderBy('distance')  // ترتيب المستشفيات حسب المسافة
            ->limit(3)  // أخذ أقرب 3 مستشفيات
            ->get();
        
        
        
    
            // Logging للـ Hospitals المسترجعة
            Log::debug('Found Hospitals: ', $hospitals->toArray());
        } catch (\Exception $e) {
            // Logging للأخطاء في حال فشل الاستعلام
            Log::error('Error while retrieving hospitals: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve hospitals'], 500);
        }
    
        // إرسال إشعارات للمستشفيات القريبة
        $notificationsSent = 0; // عداد لتتبع عدد الإشعارات المرسلة
        foreach ($hospitals as $hospital) {
            try {
                // Logging عندما يبدأ إرسال الإشعار
                Log::debug("Sending notification to hospital ID: {$hospital->id}, Name: {$hospital->name}");
    
                // إنشاء إشعار جديد لكل مستشفى
                $notification = new Notification();
                $notification->user_id = $userId; // معرف المستخدم
                $notification->created_by = $userId; // يمكن أن يكون الشخص الذي أرسل الطلب
                $notification->title = 'طلب إسعاف قريب';
                $notification->message = "يوجد طلب إسعاف بالقرب من مستشفاكم في الإحداثيات: ($latitude, $longitude)";
                $notification->type = 'ambulance'; // نوع الإشعار
                $notification->is_read = 0; // إشعار غير مقروء
    
                // حفظ الإشعار
                $notification->save();
                $notificationsSent++;
    
                // Logging بعد نجاح إرسال الإشعار
                Log::debug("Notification sent to hospital ID: {$hospital->id}");
            } catch (\Exception $e) {
                // Logging في حال حدوث خطأ أثناء حفظ الإشعار
                Log::error("Error sending notification to hospital ID: {$hospital->id}, Error: " . $e->getMessage());
            }
        }
    
        // Logging لعدد الإشعارات المرسلة
        Log::debug('Notifications sent: ' . $notificationsSent);
    
        // إرسال استجابة للمستخدم
        if ($notificationsSent > 0) {
            return response()->json([
                'message' => "$notificationsSent إشعار تم إرساله بنجاح.",
                'hospitals' => $hospitals,
            ]);
        } else {
            return response()->json([
                'message' => 'لم يتم إرسال أي إشعارات.',
                'hospitals' => $hospitals,
            ]);
        }
    }
    
}
