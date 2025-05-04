<?php

namespace App\Http\Controllers;



use App\Models\User;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\SupabaseService; // تأكد من المسار الصحيح
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // تأكد من إضافة هذه السطر


class PatientController extends Controller
{
    protected $supabaseService;

    public function __construct(SupabaseService $supabaseService)
    {
        $this->supabaseService = $supabaseService;
        $this->middleware('auth:api', ['except' => ['register']]);
    }
    public function register(Request $request)
    {
        Log::info('بدأت عملية تسجيل المريض');
    
        // التحقق من البيانات المدخلة
        $validatedData = $request->validate([
            'patient_name'  => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:6',
            'patient_phone' => 'required|string|max:15|unique:patients,patient_phone',
            'patient_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
    
        Log::info('البيانات المدخلة تم التحقق منها بنجاح', ['data' => $validatedData]);
    
        return DB::transaction(function () use ($validatedData, $request) {
            Log::info('بدأت عملية حفظ المستخدم في جدول users');
    
            // ✅ حفظ المستخدم في جدول users
            $user = User::create([
                'email'     => $validatedData['email'],
                'password'  => Hash::make($validatedData['password']),
                'user_type' => 'patient',
            ]);
    
            Log::info('تم حفظ المستخدم في جدول users', ['user' => $user]);
    
            // ✅ تحميل الصورة إن وجدت
            $imagePath = null;
            if ($request->hasFile('patient_image')) {
                Log::info('تم العثور على صورة المريض');
                $imagePath = $request->file('patient_image')->store('patient_images', 'public');
            }
    
            // ✅ حفظ المريض في جدول patients
            $patient = Patient::create([
                'patient_name'  => $validatedData['patient_name'],
                'patient_phone' => $validatedData['patient_phone'],
                'patient_image' => $imagePath,
                'user_id' => $user->user_id,
            ]);
    
            Log::info('تم حفظ المريض في جدول patients', ['patient' => $patient]);
    
            // ✅ تحديث user_id في جدول users
            $user->update(['patient_id' => $patient->id]);
    
            // ✅ توليد JWT Token بعد التسجيل
            $token = JWTAuth::fromUser($user);
            $user->patient_id = $patient->patient_id;
            $user->save();
    
            Log::info('تم توليد توكن الـ JWT', ['token' => $token]);
    
            // ✅ إرسال رسالة التحقق عبر البريد الإلكتروني باستخدام Supabase
            Log::info('بدأت عملية إرسال رسالة التحقق عبر البريد الإلكتروني');
    
            // استدعاء دالة التسجيل في Supabase
            // استخدام البيانات المحققة في هذا الجزء
            $supabaseResponse = $this->supabaseService->signUp(
                $validatedData['email'], 
                $validatedData['password'], 
                $validatedData['patient_phone']
            );
    
            // إذا كانت هناك مشكلة في التسجيل، سجّل الخطأ.
            if (isset($supabaseResponse['error'])) {
                Log::error('فشل الاتصال بـ Supabase', ['error' => $supabaseResponse['error']]);
            } else {
                Log::info('تم التسجيل بنجاح في Supabase', ['data' => $supabaseResponse['data']]);
            }
    
            Log::info('تم إرسال رسالة التحقق بنجاح عبر البريد الإلكتروني');
    
            return response()->json([
                'message' => 'تم تسجيل المريض بنجاح. تحقق من بريدك الإلكتروني.',
                'patient' => $patient,
                'user'    => $user,
                'token'   => $token,
            ], 201);
        });
    }
    
    

    // ✅ استعلام عن جميع المرضى
    public function index()
    {
        $patients = Patient::with('user:user_id,email')->get();
    
        $result = $patients->map(function ($patient) {
            return [
                'patient_id'         => $patient->patient_id,
                'user_id'            => $patient->user_id,
                'patient_name'       => $patient->patient_name,
                'patient_age'        => $patient->patient_age,
                'patient_birthdate'  => $patient->patient_birthdate,
                'patient_blood_type' => $patient->patient_blood_type,
                'patient_phone'      => $patient->patient_phone,
                'patient_address'    => $patient->patient_address,
                'patient_status'     => $patient->patient_status,
                'patient_height'     => $patient->patient_height,
                'patient_weight'     => $patient->patient_weight,
                'patient_nationality'=> $patient->patient_nationality,
                'patient_gender'     => $patient->patient_gender,
                'patient_image'      => $patient->patient_image,
                'patient_notes'      => $patient->patient_notes,
                'created_at'         => $patient->created_at,
                'updated_at'         => $patient->updated_at,
                'email'              => $patient->user->email ?? null, // ← هنا الإيميل
            ];
        });
    
        return response()->json(['patients' => $result], 200);
    }
    // ✅ استعلام عن مريض معين
    public function show($id)
    {
        $patient = Patient::find($id);
        return $patient ? response()->json(['patient' => $patient], 200)
                         : response()->json(['message' => 'المريض غير موجود'], 404);
        $patients = Patient::with('user:user_id,email')->get();
    
        $result = $patients->map(function ($patient) {
            return [
                'patient_id'         => $patient->patient_id,
                'user_id'            => $patient->user_id,
                'patient_name'       => $patient->patient_name,
                'patient_age'        => $patient->patient_age,
                'patient_birthdate'  => $patient->patient_birthdate,
                'patient_blood_type' => $patient->patient_blood_type,
                'patient_phone'      => $patient->patient_phone,
                'patient_address'    => $patient->patient_address,
                'patient_status'     => $patient->patient_status,
                'patient_height'     => $patient->patient_height,
                'patient_weight'     => $patient->patient_weight,
                'patient_nationality'=> $patient->patient_nationality,
                'patient_gender'     => $patient->patient_gender,
                'patient_image'      => $patient->patient_image,
                'patient_notes'      => $patient->patient_notes,
                'created_at'         => $patient->created_at,
                'updated_at'         => $patient->updated_at,
                'email'              => $patient->user->email ?? null, // ← هنا الإيميل
            ];
        });
    
        return response()->json(['patients' => $result], 200);
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
                'patient_id'=>$patient->patient_id,
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
// دالة لإرجاع عدد المرضى
public function getPatientCount()
{
    // إرجاع عدد المرضى فقط
    return response()->json([
        'patient_count' => Patient::count()
    ]);
}
}
