<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Patient;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class PatientController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register']]);
    }
   
        // ✅ تسجيل مريض جديد
        public function register(Request $request)
        {
            $validatedData = $request->validate([
                'patient_name'  => 'required|string|max:255',
                'email'         => 'required|email|unique:users,email',
                'password'      => 'required|min:6',
                'patient_phone' => 'required|string|max:15|unique:patients,patient_phone',
                'patient_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);
    
            return DB::transaction(function () use ($validatedData, $request) {
                // ✅ حفظ المستخدم في جدول users
                $user = User::create([
                    //'name'      => $validatedData['patient_name'],
                    'email'     => $validatedData['email'],
                    'password'  => Hash::make($validatedData['password']),
                    'user_type' => 'patient',
                ]);
    
                // ✅ تحميل الصورة إن وجدت
                $imagePath = null;
                if ($request->hasFile('patient_image')) {
                    $imagePath = $request->file('patient_image')->store('patient_images', 'public');
                }
    
                // ✅ حفظ المريض في جدول patients
                $patient = Patient::create([
                    'patient_name'  => $validatedData['patient_name'],
                    'patient_phone' => $validatedData['patient_phone'],
                    'patient_image' => $imagePath,
                    'user_id' => $user->user_id,
                ]);
    
                // ✅ تحديث user_id في جدول users
                $user->update(['patient_id' => $patient->id]);
    
                // ✅ توليد JWT Token بعد التسجيل
                $token = JWTAuth::fromUser($user);
                $user->patient_id = $patient->patient_id;
                $user->save();
                return response()->json([
                    'message' => 'تم تسجيل المريض بنجاح',
                    'patient' => $patient,
                    'user'    => $user,
                    'token'   => $token, // ⬅️ يتم إرجاع التوكن مباشرة
                ], 201);
            });
        }
    
    


    // ✅ استعلام عن جميع المرضى
    public function index()
    {
        return response()->json(['patients' => Patient::all()], 200);
    }

    // ✅ استعلام عن مريض معين
    public function show($id)
    {
        $patient = Patient::find($id);
        return $patient ? response()->json(['patient' => $patient], 200)
                         : response()->json(['message' => 'المريض غير موجود'], 404);
    }
  
    // ✅ جلب صورة واسم المريض

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
    


    
    


    // ✅ تحديث بيانات المريض
    
    
    public function update(Request $request, $id)
    {
        try {
            // البحث عن المريض
            $patient = Patient::findOrFail($id);
            $user = User::findOrFail($patient->user_id); // جلب المستخدم المرتبط بالمريض
    
            // التحقق من وجود صورة مرفوعة
            if ($request->hasFile('patient_image')) {
                // حفظ الصورة الجديدة
                $imagePath = $request->file('patient_image')->store('patient_images', 'public');
                $patient->patient_image = $imagePath;
            }
    
            // تحديث جميع البيانات المدخلة باستثناء الحقول الخاصة بالمستخدم
            $patient->fill($request->except(['patient_image', 'email', 'password']))->save();
    
            // تحديث البريد الإلكتروني إذا تم إرساله
            if ($request->has('email')) {
                $user->email = $request->email;
            }
    
            // تحديث كلمة المرور إذا تم إرسالها
            if ($request->has('password')) {
                $user->password = bcrypt($request->password);
            }
    
            // حفظ بيانات المستخدم
            $user->save();
    
            return response()->json([
                'message' => 'تم التحديث بنجاح',
                'updated_patient' => $patient,
                'updated_user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء التحديث', 'details' => $e->getMessage()], 500);
        }
    }
    



    // ✅ حذف مريض
    public function destroy($id)
    {
        $patient = Patient::find($id);
        if (!$patient) {
            return response()->json(['message' => 'المريض غير موجود'], 404);
        }

        return DB::transaction(function () use ($patient) {
            // حذف الصورة إذا كانت موجودة
            if ($patient->patient_image) {
                Storage::disk('public')->delete($patient->patient_image);
            }

            // حذف المستخدم المرتبط
            User::where('user_id', $patient->user_id)->delete();
            
            // حذف المريض
            $patient->delete();

            return response()->json(['message' => 'تم حذف المريض بنجاح'], 200);
        });
    }
}
