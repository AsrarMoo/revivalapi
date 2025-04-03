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
use App\Models\PendingDoctor;
use Illuminate\Support\Facades\Mail;

class DoctorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'index', 'show']]);
    }
   
    
    public function registerDoctor(Request $request)
{
    $validatedData = $request->validate([
        'doctor_name'         => 'required|string|max:255',
        'email'               => 'required|email|unique:pending_doctors,email',
        'password'            => 'required|min:6',
        'phone'               => 'required|string|max:15|unique:pending_doctors,phone',
        'gender'              => 'required|in:Male,Female',
        'specialty_id'        => 'required|integer|exists:specialties,specialty_id',
        'qualification'       => 'required|string|max:255',
        'experience'          => 'required|integer|min:0',
        'bio'                 => 'nullable|string',
        'license'             => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'certificate'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'image'               => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    return DB::transaction(function () use ($validatedData, $request) {
        // ✅ حفظ الملفات
        $licensePath = $request->file('license')->store('doctor_licenses', 'public');
        $certificatePath = $request->hasFile('certificate') ? 
                           $request->file('certificate')->store('doctor_certificates', 'public') : null;
        $imagePath = $request->hasFile('image') ? 
                     $request->file('image')->store('doctor_images', 'public') : null;

        // ✅ حفظ الطلب في pending_doctors
        $pendingDoctor = PendingDoctor::create([
            'doctor_name'    => $validatedData['doctor_name'],
            'email'          => $validatedData['email'],
            'phone'          => $validatedData['phone'],
            'gender'         => $validatedData['gender'],
            'specialty_id'   => $validatedData['specialty_id'],
            'qualification'  => $validatedData['qualification'],
            'experience'     => $validatedData['experience'],
            'bio'            => $validatedData['bio'] ?? null,
            'license_path'   => $licensePath,
            'certificate_path' => $certificatePath,
            'image_path'     => $imagePath,
            'status'         => 'pending',
        ]);

        // ✅ إرسال إشعار إلى وزارة الصحة
        DB::table('notifications')->insert([
            'user_id' => 1, // استبدله بـ ID حساب وزارة الصحة
            'type'    => 'doctor_registration',
            'message' => "تم تقديم طلب تسجيل طبيب جديد: {$validatedData['doctor_name']}",
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إرسال طلبك إلى الوزارة. سيتم إعلامك عند الموافقة.',
            'pending_doctor' => $pendingDoctor,
        ], 201);
    });
}


public function approveDoctor($id)
{
    return DB::transaction(function () use ($id) {
        $pendingDoctor = PendingDoctor::findOrFail($id);

        // ✅ إنشاء مستخدم جديد في users
        $user = User::create([
            'email'    => $pendingDoctor->email,
            'password' => Hash::make('DefaultPassword123'), // يمكن تغييره لاحقًا
            'user_type'=> 'doctor',
        ]);

        // ✅ إنشاء حساب الطبيب في doctors
        $doctor = Doctor::create([
            'doctor_name'         => $pendingDoctor->doctor_name,
            'doctor_phone'        => $pendingDoctor->phone,
            'doctor_image'        => $pendingDoctor->image_path,
            'doctor_gender'       => $pendingDoctor->gender,
            'specialty_id'        => $pendingDoctor->specialty_id,
            'doctor_qualification'=> $pendingDoctor->qualification,
            'doctor_experience'   => $pendingDoctor->experience,
            'doctor_bio'          => $pendingDoctor->bio,
            'user_id'             => $user->user_id,
        ]);

        // ✅ تحديث user_id في جدول users
        $user->update(['doctor_id' => $doctor->doctor_id]);

        // ✅ حذف السجل من pending_doctors
        $pendingDoctor->delete();

        // ✅ إرسال بريد إلكتروني للطبيب عند الموافقة
        Mail::raw("تمت الموافقة على تسجيلك كطبيب في النظام. يمكنك الآن تسجيل الدخول.", function ($message) use ($pendingDoctor) {
            $message->to($pendingDoctor->email)
                    ->subject('تمت الموافقة على حسابك');
        });

        return response()->json([
            'message' => 'تمت الموافقة على الطبيب بنجاح!',
            'doctor'  => $doctor,
            'user'    => $user,
        ]);
    });
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
    public function create(Request $request)
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
    


}
  