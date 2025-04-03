<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PendingDoctor;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DoctorApprovalNotification;
use Kreait\Firebase\Contract\Messaging;

class DoctorRegistrationController extends Controller
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    // 1️⃣ تسجيل الطبيب وإرسال إشعار لوزارة الصحة
    public function registerDoctor(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:pending_doctors,phone',
            'gender' => 'required|in:Male,Female',
            'specialty_id' => 'required|exists:specialties,id',
            'qualification' => 'required|string|max:255',
            'experience' => 'required|integer|min:0|max:99',
            'bio' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:255',
            'attachment' => 'required|string|max:255',
            'password' => 'required|min:6',
        ]);

        DB::beginTransaction();
        try {
            // حفظ بيانات الطبيب في جدول pending_doctors
            $pendingDoctor = PendingDoctor::create([
                'doctor_name' => $request->name,
                'doctor_email' => $request->email,
                'doctor_phone' => $request->phone,
                'doctor_gender' => $request->gender,
                'specialty_id' => $request->specialty_id,
                'doctor_qualification' => $request->qualification,
                'doctor_experience' => $request->experience,
                'doctor_bio' => $request->bio,
                'doctor_image' => $request->image,
                'attachment' => $request->attachment,
                'password' => bcrypt($request->password),
            ]);

            // إرسال إشعار إلى وزارة الصحة عبر Firebase
            $this->sendFirebaseNotification("طلب تسجيل جديد", "هناك طبيب جديد بانتظار الموافقة.");

            DB::commit();
            return response()->json(["message" => "تم إرسال الطلب بنجاح، سيتم التواصل معك قريبًا."], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["error" => "حدث خطأ أثناء التسجيل."], 500);
        }
    }

    // 2️⃣ دالة لموافقة وزارة الصحة على الطبيب
    public function approveDoctor($id)
    {
        $pendingDoctor = PendingDoctor::find($id);
        if (!$pendingDoctor) {
            return response()->json(["error" => "الطبيب غير موجود أو تم قبوله مسبقًا."], 404);
        }

        DB::beginTransaction();
        try {
            // نقل بيانات الطبيب إلى جدول الأطباء
            $doctor = Doctor::create([
                'doctor_name' => $pendingDoctor->doctor_name,
                'doctor_email' => $pendingDoctor->doctor_email,
                'doctor_phone' => $pendingDoctor->doctor_phone,
                'doctor_gender' => $pendingDoctor->doctor_gender,
                'specialty_id' => $pendingDoctor->specialty_id,
                'doctor_qualification' => $pendingDoctor->doctor_qualification,
                'doctor_experience' => $pendingDoctor->doctor_experience,
                'doctor_bio' => $pendingDoctor->doctor_bio,
                'doctor_image' => $pendingDoctor->doctor_image,
                'attachment' => $pendingDoctor->attachment,
            ]);

            // إنشاء حساب للمستخدم في جدول users
            $user = User::create([
                'name' => $pendingDoctor->doctor_name,
                'email' => $pendingDoctor->doctor_email,
                'password' => $pendingDoctor->password,
                'user_type' => 'doctor',
                'doctor_id' => $doctor->id,
            ]);

            // حذف الطبيب من جدول الانتظار
            $pendingDoctor->delete();

            // إرسال إشعار للطبيب عبر Firebase
            $this->sendFirebaseMessage($doctor->doctor_phone, "تم قبول طلبك، يمكنك الآن تسجيل الدخول.");

            DB::commit();
            return response()->json(["message" => "تمت الموافقة على الطبيب بنجاح."], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["error" => "حدث خطأ أثناء الموافقة."], 500);
        }
    }

    // 3️⃣ دالة لإرسال إشعار عبر Firebase
    private function sendFirebaseNotification($title, $body)
    {
        $message = CloudMessage::fromArray([
            'notification' => ['title' => $title, 'body' => $body],
            'topic' => 'health_ministry',
        ]);
        $this->messaging->send($message);
    }

    // 4️⃣ دالة لإرسال رسالة SMS عبر Firebase
    private function sendFirebaseMessage($phone, $message)
    {
        // هنا يمكن استدعاء Firebase SMS API لإرسال الرسالة للطبيب
    }
}
