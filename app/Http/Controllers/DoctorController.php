<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Doctor;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class DoctorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'index', 'show']]);
    }

 // ✅ جلب جميع الأطباء مع اسم التخصص
public function index()
{
    $doctors = Doctor::with('specialty:specialty_id,specialty_name')->get();
    return response()->json($doctors);
}

// ✅ جلب بيانات الطبيب بناءً على التوكن
public function show()
{
    try {
        // 🔹 جلب بيانات المستخدم من التوكن
        $user = auth()->user();

        // 🔹 التحقق من أن المستخدم هو طبيب
        if ($user->user_type !== 'doctor') {
            return response()->json(['message' => 'المستخدم ليس طبيبًا'], 403);
        }

        // 🔹 جلب بيانات الطبيب باستخدام user_id من التوكن
        $doctor = Doctor::with('specialty:specialty_id,specialty_name')
                        ->where('user_id', $user->user_id)
                        ->first();

        // 🔹 التحقق من وجود الطبيب
        if (!$doctor) {
            return response()->json(['message' => 'لم يتم العثور على الطبيب'], 404);
        }

        // 🔹 إضافة رابط الصورة كامل
        $doctor->doctor_image = url('storage/' . $doctor->doctor_image);

        // 🔹 جلب الإيميل من جدول users بناءً على user_id
        $userEmail = \App\Models\User::where('user_id', $user->user_id)->value('email');

        // ✅ إضافة الإيميل إلى بيانات الطبيب
        $doctor->doctor_email = $userEmail;

        // ✅ إرجاع بيانات الطبيب مع رابط الصورة الكامل والإيميل
        return response()->json($doctor);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'حدث خطأ أثناء جلب بيانات الطبيب',
            'details' => $e->getMessage(),
        ], 500);
    }
}



    // ✅ تسجيل طبيب جديد
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'doctor_name'         => 'required|string|max:255',
            'email'               => 'required|email|unique:users,email',
            'password'            => 'required|min:6',
            'doctor_phone'        => 'required|string|max:15|unique:doctors,doctor_phone',
            'doctor_image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'doctor_gender'       => 'required|in:Male,Female',
            'specialty_id'        => 'required|integer|exists:specialties,specialty_id',
            'doctor_qualification'=> 'required|string|max:255',
            'doctor_experience'   => 'required|integer|min:0',
            'doctor_bio'          => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validatedData, $request) {
            // ✅ حفظ المستخدم في جدول users
            $user = User::create([
                //'name'      => $validatedData['doctor_name'],
                'email'     => $validatedData['email'],
                'password'  => Hash::make($validatedData['password']),
                'user_type' => 'doctor',
            ]);

            // ✅ تحميل الصورة إن وجدت
            $imagePath = null;
            if ($request->hasFile('doctor_image')) {
                $imagePath = $request->file('doctor_image')->store('doctor_images', 'public');
            }

            // ✅ حفظ الطبيب في جدول doctors
            $doctor = Doctor::create([
                'doctor_name'         => $validatedData['doctor_name'],
                'doctor_phone'        => $validatedData['doctor_phone'],
                'doctor_image'        => $imagePath,
                'doctor_gender'       => $validatedData['doctor_gender'],
                'specialty_id'        => $validatedData['specialty_id'],
                'doctor_qualification'=> $validatedData['doctor_qualification'],
                'doctor_experience'   => $validatedData['doctor_experience'],
                'doctor_bio'          => $validatedData['doctor_bio'] ?? null,
                'user_id'             => $user->user_id,
            ]);

            // ✅ تحديث user_id في جدول users
            $user->update(['doctor_id' => $doctor->doctor_id]);

            // ✅ توليد JWT Token بعد التسجيل
            $token = JWTAuth::fromUser($user);
            return response()->json([
                'message' => 'تم تسجيل الطبيب بنجاح',
                'doctor'  => $doctor,
                'user'    => $user,
                'token'   => $token,
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        try {
            // 🔹 البحث عن الطبيب
            $doctor = Doctor::findOrFail($id);
            $user = User::findOrFail($doctor->user_id);
            $doctor->specialty_id = $request->specialty_id;
    
            // 🔹 التحقق من البيانات المدخلة مع تصحيح خطأ `unique`
            $validatedData = $request->validate([
                'doctor_name'          => 'sometimes|string|max:255',
                'email'                => 'sometimes|email|unique:users,email,' . $user->user_id . ',user_id',
                'password'             => 'sometimes|min:6',
                'doctor_phone'         => 'sometimes|string|max:15|unique:doctors,doctor_phone,' . $doctor->doctor_id . ',doctor_id',
                'doctor_image'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'doctor_gender'        => 'sometimes|in:Male,Female',
                'specialty_id'         => 'sometimes|integer|exists:specialties,specialty_id',
                'doctor_qualification' => 'sometimes|string|max:255',
                'doctor_experience'    => 'sometimes|integer|min:0',
                'doctor_bio'           => 'nullable|string',
            ]);
    
            // 🔹 تحديث الصورة إذا تم رفعها
            if ($request->hasFile('doctor_image')) {
                $imagePath = $request->file('doctor_image')->store('doctor_images', 'public');
    
                // حذف الصورة القديمة إذا كانت موجودة
                if ($doctor->doctor_image) {
                    Storage::disk('public')->delete($doctor->doctor_image);
                }
    
                $doctor->doctor_image = $imagePath;
            }
    
            // 🔹 تحديث بيانات الطبيب
            $doctor->fill($validatedData)->save();
    
            // 🔹 تحديث بيانات المستخدم (الإيميل وكلمة المرور)
            if ($request->has('email')) {
                $user->email = $request->email;
            }
    
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
    
            $user->save();
    
            return response()->json([
                'message' => 'تم التحديث بنجاح',
                'updated_doctor' => [
                    'doctor_name' => $doctor->doctor_name,
                    'doctor_phone' => $doctor->doctor_phone,
                    'doctor_gender' => $doctor->doctor_gender,
                    'doctor_image' => $doctor->doctor_image,
                    'specialty_id' => $doctor->specialty_id,
                    'doctor_qualification' => $doctor->doctor_qualification,
                    'doctor_experience' => $doctor->doctor_experience,
                    'doctor_bio' => $doctor->doctor_bio,
                ],
                'updated_user' => [
                    'email' => $user->email
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء التحديث', 'details' => $e->getMessage()], 500);
        }
    }
    // ✅ حذف طبيب
    public function destroy($id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json(['message' => 'لم يتم العثور على الطبيب'], 404);
        }

        $user = User::where('doctor_id', $doctor->doctor_id)->first();

        return DB::transaction(function () use ($doctor, $user) {
            if ($doctor->doctor_image) {
                Storage::disk('public')->delete($doctor->doctor_image);
            }

            if ($user) {
                $user->delete();
            }

            $doctor->delete();

            return response()->json(['message' => 'تم حذف الطبيب بنجاح'], 200);
        });
    }
    
public function getProfile()
    {
        try {
            // 🔹 جلب بيانات المستخدم من التوكن
            $user = auth()->user();
    
            // 🔹 التحقق من أن المستخدم مسجل كمريض
            if ($user->user_type !== 'patient') {
                return response()->json(['error' => 'المستخدم ليس مريضًا'], 403);
            }
    
            // 🔹 جلب بيانات المريض من جدول patients باستخدام user_id
            $patient = Patient::where('user_id', $user->user_id)->first();
    
            // 🔹 التحقق مما إذا كانت بيانات المريض موجودة
            if (!$patient) {
                return response()->json(['error' => 'لم يتم العثور على بيانات المريض'], 404);
            }
    
            // ✅ إرجاع بيانات المريض
            return response()->json([
                'name' => $patient->patient_name,
                'image' => $patient->patient_image,
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء جلب بيانات الملف الشخصي',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    



    public function profile()
    {
        try {
            // 🔹 جلب بيانات المستخدم من التوكن
            $user = auth()->user();
    
            // لوج: تأكد من أن المستخدم مسجل كـ "طبيب"
            Log::debug('تم جلب التوكن للمستخدم:', ['user_id' => $user->user_id]);
    
            // 🔹 التحقق من أن المستخدم مسجل كـ "طبيب"
            if ($user->user_type !== 'doctor') {
                Log::debug('المستخدم ليس طبيبًا', ['user_id' => $user->user_id]);
                return response()->json(['error' => 'المستخدم ليس طبيبًا'], 403);
            }
    
            // 🔹 جلب بيانات الطبيب من جدول doctors باستخدام user_id
            $doctor = Doctor::with('specialty')->where('user_id', $user->user_id)->first();
            
            // لوج: إذا لم يتم العثور على الطبيب
            if (!$doctor) {
                Log::debug('لم يتم العثور على الطبيب باستخدام user_id', ['user_id' => $user->user_id]);
                return response()->json(['error' => 'لم يتم العثور على الطبيب'], 404);
            }
    
            // ✅ إرجاع بيانات الطبيب مع التخصص
            Log::debug('تم العثور على الطبيب:', ['doctor' => $doctor]);
    
            return response()->json([
                'doctor_name' => $doctor->doctor_name,
                'doctor_gender' => $doctor->doctor_gender,
                'specialty' => $doctor->specialty,
                'doctor_qualification' => $doctor->doctor_qualification,
                'doctor_experience' => $doctor->doctor_experience,
                'doctor_phone' => $doctor->doctor_phone,
                'doctor_bio' => $doctor->doctor_bio,
                'doctor_image' => $doctor->doctor_image,
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('حدث خطأ أثناء جلب بيانات الملف الشخصي', [
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'error' => 'حدث خطأ أثناء جلب بيانات الملف الشخصي',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    public function createDoctorAccount(Request $request)
{
    // ✅ التحقق من البيانات المدخلة
    $validatedData = $request->validate([
        'doctor_name'         => 'required|string|max:255',
        'email'               => 'required|email|unique:users,email',
        'password'            => 'required|min:6',
        'doctor_phone'        => 'required|string|max:15|unique:doctors,doctor_phone',
        'doctor_image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'doctor_gender'       => 'required|in:Male,Female',
        'specialty_id'        => 'required|integer|exists:specialties,specialty_id',
        'doctor_qualification'=> 'required|string|max:255',
        'doctor_experience'   => 'required|integer|min:0',
        'doctor_bio'          => 'nullable|string',
        'attachment'          => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:2048',  // إضافة التحقق من المرفقات
    ]);

    // ✅ إرسال طلب الموافقة إلى وزارة الصحة
    $healthMinistryApproval = $this->requestHealthMinistryApproval($validatedData);

    if (!$healthMinistryApproval) {
        return response()->json([
            'message' => 'تم رفض طلبك من وزارة الصحة. يرجى المحاولة لاحقًا.',
        ], 400);
    }

    // ✅ بدء المعاملة مع قاعدة البيانات فقط بعد الموافقة
    return DB::transaction(function () use ($validatedData, $request) {

        // ✅ حفظ المستخدم في جدول users
        $user = User::create([
            'email'     => $validatedData['email'],
            'password'  => Hash::make($validatedData['password']),
            'user_type' => 'doctor',
        ]);

        // ✅ تحميل الصورة إن وجدت
        $imagePath = null;
        if ($request->hasFile('doctor_image')) {
            $imagePath = $request->file('doctor_image')->store('doctor_images', 'public');
        }

        // ✅ تحميل المرفق إن وجد
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('doctor_attachments', 'public');
        }

        // ✅ حفظ الطبيب في جدول doctors
        $doctor = Doctor::create([
            'doctor_name'         => $validatedData['doctor_name'],
            'doctor_phone'        => $validatedData['doctor_phone'],
            'doctor_image'        => $imagePath,
            'doctor_gender'       => $validatedData['doctor_gender'],
            'specialty_id'        => $validatedData['specialty_id'],
            'doctor_qualification'=> $validatedData['doctor_qualification'],
            'doctor_experience'   => $validatedData['doctor_experience'],
            'doctor_bio'          => $validatedData['doctor_bio'] ?? null,
            'attachment'          => $attachmentPath,  // حفظ المرفق
            'user_id'             => $user->user_id,
        ]);

        // ✅ توليد JWT Token للمستخدم
        $token = JWTAuth::fromUser($user);

        // ✅ إرسال إشعار لوزارة الصحة
        $this->sendNotificationToHealthMinistry($doctor);

        // ✅ إرجاع بيانات الطبيب مع التوكن
        return response()->json([
            'message' => 'تم إنشاء حساب الطبيب بنجاح',
            'doctor'  => $doctor,
            'user'    => $user,
            'token'   => $token,
        ], 201);
    });
}

/**
 * دالة محاكاة إرسال إشعار لوزارة الصحة
 */
private function sendNotificationToHealthMinistry($doctor)
{
    // إضافة إشعار إلى جدول الشهادات أو التنبيهات
    $notification = new Notification([
        'recipient'   => 'وزارة الصحة',
        'message'     => "تم رفع مرفق من الطبيب: {$doctor->doctor_name} كتوثيق.",
        'status'      => 'unread', // حالة الإشعار
        'created_at'  => now(),
    ]);

    $notification->save();
}

}