<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Notification;
use App\Models\Specialty;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Models\PendingDoctor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class DoctorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['registerDoctor','register', 'index', 'show']]);
    }
   
    public function registerDoctor(Request $request)
    {
        Log::info('Request received to register doctor:', $request->all());
    
        $validatedData = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:pending_doctors,email',
            'password'       => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/', // القيود الخاصة بكلمة المرور
            'phone'          => 'required|string|max:15|unique:pending_doctors,phone',
            'gender'         => 'required|in:Male,Female',
            'specialty_name' => 'required|string', // اسم التخصص
            'qualification'  => 'required|string|max:255',
            'experience'     => 'required|integer|min:0',
            'bio'            => 'nullable|string',
            'certificate'    => 'nullable|file|max:5120',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ], [
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.',
            'password.regex' => 'يجب أن تحتوي كلمة المرور على حرف كبير، رقم، ورمز خاص مثل @$!%*?&.',
        ]);
    
        return DB::transaction(function () use ($validatedData, $request) {
            try {
                // ✅ حفظ الشهادة بالامتداد الأصلي
                $certificatePath = null;
                if ($request->hasFile('certificate')) {
                    $certificateFile = $request->file('certificate');
                    $certificateExtension = $certificateFile->getClientOriginalExtension();
                    $certificateName = uniqid() . '.' . $certificateExtension;
                    $certificatePath = $certificateFile->storeAs('doctor_certificates', $certificateName, 'public');
                }
    
                // ✅ حفظ الصورة بالامتداد الأصلي
                 $imagePath = $request->hasFile('image') ? 
                             $request->file('image')->store('doctor_images', 'public') : null;
                // ✅ التحقق من وجود التخصص
                $specialty = Specialty::where('specialty_name', $validatedData['specialty_name'])->first();
                if (!$specialty) {
                    return response()->json([
                        'message' => 'التخصص غير موجود!',
                    ], 400);
                }
    
                // ✅ إنشاء سجل في جدول pending_doctors
                $pendingDoctor = PendingDoctor::create([
                    'name'            => $validatedData['name'],
                    'email'           => $validatedData['email'],
                    'password'        => Hash::make($validatedData['password']),
                    'phone'           => $validatedData['phone'],
                    'gender'          => $validatedData['gender'],
                    'specialty_id'    => $specialty->specialty_id,
                    'qualification'   => $validatedData['qualification'],
                    'experience'      => $validatedData['experience'],
                    'bio'             => $validatedData['bio'] ?? null,
                    'certificate_path'=> $certificatePath,
                    'image_path'      => $imagePath,
                    'status'          => 'pending',
                ]);
    
                // ✅ إرسال إشعار لوزارة الصحة
                Notification::create([
                    'user_id'           => 47, // ID وزارة الصحة
                  'request_id' => $pendingDoctor->id,
                    'type'              => 'Requesting',
                    'title'             => 'طلب تسجيل طبيب جديد',
                    'message'           => "تم تقديم طلب تسجيل طبيب جديد: {$validatedData['name']} (التخصص: {$specialty->specialty_name}).",
                    'is_read'           => 0,
                    'pending_doctor_id' => $pendingDoctor->id,
                    'created_at'        => now(),
                ]);
    
                return response()->json([
                    'message'        => 'تم إرسال طلبك إلى وزارة الصحة، سيتم إعلامك عند الموافقة.',
                    'pending_doctor' => $pendingDoctor,
                ], 201);
    
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'حدث خطأ أثناء تسجيل الطبيب.',
                    'error'   => $e->getMessage()
                ], 500);
            }
        });
    }
    public function approveDoctor($request_id)
    {
        $notification = Notification::where('request_id', $request_id)->first();
    
        if (!$notification) {
            return response()->json(['message' => 'الإشعار غير موجود'], 404);
        }
    
        $request_id = $notification->request_id;
    
        Log::info('البحث عن الطبيب في جدول pending_doctors حسب request_id', ['request_id' => $request_id]);
    
        $pendingDoctor = PendingDoctor::where('id', $request_id)->first();
    
        if (!$pendingDoctor) {
            Log::warning('الطبيب غير موجود في قائمة الانتظار', ['request_id' => $request_id]);
            return response()->json(['message' => 'الطبيب غير موجود في قائمة الانتظار.'], 404);
        }
    
        // سجل بيانات الطبيب المعلق قبل الترحيل
        Log::info('بيانات الطبيب المعلق قبل الترحيل', [
            'name' => $pendingDoctor->name,
            'email' => $pendingDoctor->email,
            'certificate_path' => $pendingDoctor->certificate_path, // الشهادة
            'image_path' => $pendingDoctor->image_path, // الصورة
            'gender' => $pendingDoctor->gender, // الجنس
            'qualification' => $pendingDoctor->qualification, // المؤهل
            'bio' => $pendingDoctor->bio, // السيرة الذاتية
            'phone' => $pendingDoctor->phone, // الهاتف
        ]);
    
        return DB::transaction(function () use ($pendingDoctor, $notification) {
            try {
                $user = User::create([
                    'email'     => $pendingDoctor->email,
                    'password'  => $pendingDoctor->password,
                    'user_type' => 'doctor',
                ]);
    
                $doctor = Doctor::create([
                    'doctor_name'           => $pendingDoctor->name,
                    'specialty_id'          => $pendingDoctor->specialty_id,
                    'doctor_qualification'  => $pendingDoctor->qualification,
                    'doctor_experience'     => $pendingDoctor->experience,
                    'doctor_bio'            => $pendingDoctor->bio,
                    'password' => $pendingDoctor->password, // لا تعيد تشفيرها
                    'doctor_certificate'    => $pendingDoctor->certificate_path, // تأكد من أن الشهادة هنا
                    'doctor_image'          => $pendingDoctor->image_path,
                    'doctor_phone'          => $pendingDoctor->phone,
                    'doctor_gender'         => $pendingDoctor->gender, // الجنس
                    'user_id'               => $user->user_id,
                ]);
    
                // سجل بيانات الطبيب بعد الترحيل
                Log::info('تم ترحيل بيانات الطبيب إلى جدول doctors', [
                    'doctor_id' => $doctor->doctor_id,
                    'doctor_certificate' => $doctor->doctor_certificate, // تحقق من الحقل بعد الترحيل
                    'doctor_gender' => $doctor->doctor_gender, // الجنس
                ]);
    
                $user->update(['doctor_id' => $doctor->doctor_id]);
    
                DB::table('notifications')->insert([
                    'user_id'    => $user->user_id,
                    'request_id' => $notification->request_id,
                    'title'      => 'approval',
                    'type'       => 'approval',
                    'message'    => "تمت الموافقة على طلب تسجيلك كطبيب.",
                    'is_read'    => 0,
                    'created_at' => now(),
                ]);
    
                // ❌ لا نحذف الإشعار الأصلي
                // $notification->delete();
    
                $pendingDoctor->delete();
    
                return response()->json([
                    'title'   => 'approve',
                    'message' => 'تمت الموافقة على الطبيب بنجاح!',
                    'doctor'  => $doctor,
                    'type'    => 'approval',
                ], 200);
    
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('خطأ أثناء الموافقة', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'حدث خطأ أثناء المعالجة: ' . $e->getMessage()], 500);
            }
        });
    }
    
    
public function rejectDoctor($requestId)
{
    $notification = Notification::where('request_id', $requestId)->first();

    if (!$notification) {
        return response()->json(['message' => 'الإشعار غير موجود'], 404);
    }

    $request_id = $notification->request_id;

    Log::info('البحث عن الطبيب في جدول pending_doctors حسب request_id', ['request_id' => $request_id]);

    $pendingDoctor = PendingDoctor::where('id', $request_id)->first();

    if (!$pendingDoctor) {
        Log::warning('الطبيب غير موجود في قائمة الانتظار', ['request_id' => $request_id]);
        return response()->json(['message' => 'الطبيب غير موجود في قائمة الانتظار.'], 404);
    }

    return DB::transaction(function () use ($pendingDoctor) {
        try {
            // ❌ لا نحذف الإشعار
            // $notification->delete();

            $pendingDoctor->delete();

            return response()->json([
                'title'   => 'reject',
                'message' => 'تم رفض الطبيب بنجاح!',
                'type'    => 'rejection',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ أثناء الرفض', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'حدث خطأ أثناء المعالجة: ' . $e->getMessage()], 500);
        }
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


public function showById($doctor_id)
{
    try {
        // 🔹 التحقق من أن المعرف موجود في الطلب
        if (!$doctor_id) {
            return response()->json(['message' => 'المعرف غير موجود'], 400);
        }

        // 🔹 جلب بيانات الطبيب باستخدام doctor_id
        $doctor = Doctor::with('specialty:specialty_id,specialty_name')
                        ->where('doctor_id', $doctor_id)
                        ->first();

        // 🔹 التحقق من وجود الطبيب
        if (!$doctor) {
            return response()->json(['message' => 'لم يتم العثور على الطبيب'], 404);
        }

        // 🔹 إضافة رابط الصورة كامل
        $doctor->doctor_image = url('storage/' . $doctor->doctor_image);

        // 🔹 جلب الإيميل من جدول users بناءً على user_id
        $userEmail = \App\Models\User::where('user_id', $doctor->user_id)->value('email');

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
    

  
   
    
    public function getHospitals()
    {
        try {
            // 🔹 جلب المستخدم من التوكن
            $user = auth()->user();
    
            // 🔹 التحقق من أن المستخدم طبيب
            if ($user->user_type !== 'doctor') {
                return response()->json(['error' => 'المستخدم ليس طبيبًا'], 403);
            }
    
            // 🔹 جلب بيانات الطبيب باستخدام user_id
            $doctor = Doctor::where('user_id', $user->user_id)->first();
    
            // 🔹 التحقق من وجود الطبيب
            if (!$doctor) {
                return response()->json(['error' => 'لم يتم العثور على بيانات الطبيب'], 404);
            }
    
            // 🔹 جلب أسماء المستشفيات المرتبطة بالطبيب
            $hospitalNames = $doctor->hospitals()->pluck('hospital_name');
    
            return response()->json([
                'hospitals' => $hospitalNames
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء جلب المستشفيات',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    // ✅ دالة جديدة ترجع فقط الاسم + الصورة + التخصص
public function simpleDoctors()
{
    $doctors = Doctor::with('specialty:specialty_id,specialty_name')
        ->get()
        ->map(function ($doctor) {
            return [
                'id'=>$doctor->doctor_id,
                'name' => $doctor->doctor_name,
                'image' => $doctor->doctor_image,
                'specialty' => $doctor->specialty->specialty_name ?? 'غير محدد',
            ];
        });

    return response()->json($doctors);
}

// ✅ دالة لإرجاع عدد الأطباء
public function countDoctors()
{
    $count = Doctor::count();

    return response()->json([
        'total_doctors' => $count,
    ]);
}


}
  