<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * 📨 إرسال إشعار جديد
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,user_id',
            'user_type' => 'nullable|string|in:doctor,hospital,patient,admin',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:booking,ambulance,general',
        ]);
    
        if (!$request->user_id && !$request->user_type) {
            return response()->json(['message' => 'يجب تحديد user_id أو user_type'], 400);
        }
    
        $users = collect(); // قائمة المستخدمين المستهدفين
    
        // 🔹 إذا تم إرسال `user_id` فقط، نضيفه للقائمة
        if ($request->user_id) {
            $users->push($request->user_id);
        } 
        // 🔹 إذا تم إرسال `user_type` فقط، نبحث عن جميع المستخدمين من هذا النوع
        elseif ($request->user_type) {
            $users = \App\Models\User::where('user_type', $request->user_type)->pluck('user_id');
        }
    
        if ($users->isEmpty()) {
            return response()->json(['message' => 'لم يتم العثور على مستخدمين.'], 404);
        }
    
        $notifications = [];
    
        foreach ($users as $user_id) {
            $notifications[] = [
                'user_id' => $user_id,
                'created_by' => auth()->id(), // 🔹 يأخذ معرف المستخدم الذي أنشأ الإشعار
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'created_at' => now(),
                
            ];
        }
    
        \App\Models\Notification::insert($notifications);
    
        return response()->json([
            'message' => 'تم إرسال الإشعار بنجاح.',
            'count' => count($notifications),
        ], 201);
    }
    
    

    /**
     * 📩 1️⃣ جلب جميع الإشعارات الخاصة بالمستخدم المسجل حاليًا
     */
    public function getUserNotifications()
    {
        $userId = Auth::id();
    
        $notifications = Notification::where('user_id', $userId)
            ->orWhereNull('user_id')
            ->orderBy('created_at', 'desc')
            ->get();
    
        $notifications->transform(function ($notification) {
            $creatorName = $this->getCreatorName($notification->created_by);
    
            return [
                'notification_id' => $notification->notification_id,
                'user_id' => $notification->user_id,
                'created_by' => $creatorName, // 👈 استبدال المعرف بالاسم الصحيح
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at,
            ];
        });
    
        return response()->json([
            'user_id' => $userId,
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }
    
    /**
     * 🔍 جلب اسم منشئ الإشعار بناءً على معرفه
     */
    private function getCreatorName($userId)
    {
        if (!$userId) {
            return 'غير معروف';
        }
    
        // 🔹 جلب بيانات المستخدم
        $user = \App\Models\User::select('user_id', 'user_type')->where('user_id', $userId)->first();
        \Log::info("🔍 تحقق من المستخدم", ['user_id' => $userId, 'user_type' => $user->user_type ?? 'NULL']);
    
        if (!$user) {
            return 'غير معروف (المستخدم غير موجود)';
        }
    
        // 🔹 تحديد الجدول المناسب بناءً على نوع المستخدم
        $userTables = [
            'doctor' => ['model' => \App\Models\Doctor::class, 'field' => 'doctor_name'],
            'patient' => ['model' => \App\Models\Patient::class, 'field' => 'patient_name'],
            'hospital' => ['model' => \App\Models\Hospital::class, 'field' => 'hospital_name'],
            'healthMinistry' => ['model' => \App\Models\HealthMinistry::class, 'field' => 'health_ministry_name'], // تأكد من أن `name` هو الحقل الصحيح
        ];
    
        if (isset($userTables[$user->user_type])) {
            $model = $userTables[$user->user_type]['model'];
            $field = $userTables[$user->user_type]['field'];
    
            $record = $model::where('user_id', $userId)->first();
            if ($record) {
                \Log::info("✅ المستخدم موجود", ['name' => $record->$field]);
                return $record->$field;
            }
        }
    
        return 'مستخدم (غير معروف)';
    }
   
    /**
     * 🌍 2️⃣ جلب جميع الإشعارات في النظام (للمستشفيات ووزارة الصحة فقط)
     */
    public function getAllNotifications()
    {
        $user = Auth::user();

        // ❌ السماح فقط لوزارة الصحة والمستشفيات
        if ($user->user_type !== 'hospital' && $user->user_type !== 'healthMinistry') {
            return response()->json(['message' => 'غير مسموح لك بعرض جميع الإشعارات'], 403);
        }

        $notifications = Notification::orderBy('created_at', 'desc')->get();

        return response()->json([
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

//جلب الاشعارات للواجهة
public function getUserNotificationsview()
{
    $userId = auth()->id(); // 🔹 جلب معرف المستخدم المسجل دخوله

    $notifications = Notification::where('user_id', $userId)
        ->latest()
        ->get()
        ->map(function ($notification) {
            return [
                'creator' => $this->getCreatorName($notification->user_id),
                'title' => $notification->title,
                'message' => $notification->message,
                'sent_at' => Carbon::parse($notification->created_at)->format('Y-m-d H:i:s'),
            ];
        });

    return response()->json($notifications);
}

    
    /**
     * ✅ تحديث حالة قراءة الإشعار
     */
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id !== Auth::id() && $notification->user_id !== null) {
            return response()->json(['message' => 'غير مسموح لك بتعديل هذا الإشعار'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'تم تعليم الإشعار كمقروء']);
    }

    /**
     * 🗑 حذف إشعار (المستشفى ووزارة الصحة فقط، وللإشعارات العامة)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = Notification::findOrFail($id);
    
        // ✅ السماح للمستشفى ووزارة الصحة بحذف الإشعارات التي أنشأوها فقط
        if ($user->user_type === 'hospital' || $user->user_type === 'healthMinistry') {
            if ($notification->created_by !== $user->user_id) {
                return response()->json(['message' => 'غير مسموح لك بحذف إشعار لم تقم بإنشائه'], 403);
            }
        } else {
            return response()->json(['message' => 'غير مسموح لك بحذف الإشعارات'], 403);
        }
    
        // 🗑 حذف الإشعار
        $notification->delete();
    
        return response()->json(['message' => 'تم حذف الإشعار بنجاح']);
    }
    
}
      
