<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate incoming request
            $request->validate([
                'patient_name' => 'required|string|regex:/^(\S+\s+){4}\S+$/',  // تحقق من الاسم الخماسي
                'patient_age' => 'required|integer',
                'patient_gender' => 'required|string',
                'patient_BD' => 'required|date',
                'patient_status' => 'required|string',
                'patient_height' => 'required|numeric',
                'patient_weight' => 'required|numeric',
                'patient_phone' => 'required|string|regex:/^7\d{8}$/',  // تحقق من رقم الهاتف
                'patient_email' => 'nullable|email',
                'patient_nationality' => 'required|string',
                'patient_bloodType' => 'required|string',
                'patient_address' => 'required|string',
                'patient_image' => 'nullable|image',  // تحقق من الصورة
                'password' => 'required|string|min:8',  // تحقق من كلمة المرور
            ]);

            // Handle image upload
            if ($request->hasFile('patient_image')) {
                $imagePath = $request->file('patient_image')->store('patient_images', 'public');
            } else {
                $imagePath = null;  // إذا لم يتم رفع صورة، يتم تعيين null أو صورة افتراضية
            }

            // Create a new user record in the 'users' table
            $user = User::create([
                'name' => $request->patient_name,  // حفظ الاسم في جدول المستخدمين
                'password' => Hash::make($request->password),  // حفظ كلمة السر بشكل مشفر
                'user_type' => 'patient',  // تحديد نوع المستخدم (مريض)
                'is_active' => true,  // تعيين الحساب كنشط
            ]);

            // Create a new patient record in the 'patients' table
            $patient = Patient::create([
                'patient_name' => $request->patient_name,
                'patient_age' => $request->patient_age,
                'patient_gender' => $request->patient_gender,
                'patient_BD' => $request->patient_BD,
                'patient_status' => $request->patient_status,
                'patient_height' => $request->patient_height,
                'patient_weight' => $request->patient_weight,
                'patient_phone' => $request->patient_phone,
                'patient_email' => $request->patient_email,
                'patient_nationality' => $request->patient_nationality,
                'patient_bloodType' => $request->patient_bloodType,
                'patient_address' => $request->patient_address,
                'patient_image' => $imagePath,  // تخزين مسار الصورة
                'user_id' => $user->id,  // ربط المريض بحساب المستخدم
            ]);

            Log::info('Patient Created:', ['patient' => $patient]);

            return response()->json($patient, 201);  // إرسال استجابة مع حالة تم إنشاؤها (201)
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error creating patient:', ['error' => $e->getMessage()]);

            // Return error response
            return response()->json(['error' => 'Failed to create patient', 'message' => $e->getMessage()], 500);
        }
    }

    // استعراض جميع المرضى
    public function index()
    {
        $patients = Patient::all();  // استرجاع جميع المرضى
        return response()->json($patients, 200);
    }

    // استعراض مريض محدد
    public function show($id)
    {
        $patient = Patient::find($id);  // البحث عن المريض باستخدام المعرف
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }
        return response()->json($patient, 200);  // إرجاع بيانات المريض
    }

    // تعديل مريض
    public function update(Request $request, $id)
{
    // العثور على المريض بناءً على المعرف
    $patient = Patient::find($id);

    // إذا لم يتم العثور على المريض، إرجاع خطأ 404
    if (!$patient) {
        Log::error('Patient not found', ['patient_id' => $id]); // تسجيل الخطأ
        return response()->json(['error' => 'Patient not found'], 404);
    }

    // التحقق من البيانات المدخلة في الطلب
    $validatedData = $request->validate([
        'patient_name' => 'required|string|regex:/^(\S+\s+){4}\S+$/',  // تحقق من الاسم الخماسي
        'patient_age' => 'required|integer',
        'patient_gender' => 'required|string',
        'patient_BD' => 'required|date',
        'patient_status' => 'required|string',
        'patient_height' => 'required|numeric',
        'patient_weight' => 'required|numeric',
        'patient_phone' => 'required|string|regex:/^7\d{8}$/',  // تحقق من رقم الهاتف
        'patient_email' => 'nullable|email',
        'patient_nationality' => 'required|string',
        'patient_bloodType' => 'required|string',
        'patient_address' => 'required|string',
    ]);

    Log::info('Validated patient data', ['validated_data' => $validatedData]);

    // معالجة رفع الصورة إذا تم إرسال صورة جديدة
    if ($request->hasFile('patient_image')) {
        $imagePath = $request->file('patient_image')->store('patient_images', 'public');
        $patient->patient_image = $imagePath;  // تحديث مسار الصورة
        Log::info('Updated patient image', ['image_path' => $imagePath]);
    }

    // التحقق من القيم التي سيتم تحديثها
    $updatedFields = [
        'patient_name' => $request->patient_name,
        'patient_age' => $request->patient_age,
        'patient_gender' => $request->patient_gender,
        'patient_BD' => $request->patient_BD,
        'patient_status' => $request->patient_status,
        'patient_height' => $request->patient_height,
        'patient_weight' => $request->patient_weight,
        'patient_phone' => $request->patient_phone,
        'patient_email' => $request->patient_email,
        'patient_nationality' => $request->patient_nationality,
        'patient_bloodType' => $request->patient_bloodType,
        'patient_address' => $request->patient_address,
    ];

    Log::info('Fields to be updated', ['updated_fields' => $updatedFields]);

    // تحديث بيانات المريض
    $patient->update($updatedFields);

    // بعد التحديث، تحقق مما إذا تم تحديث المريض
    Log::info('Returning patient data with status 200', ['patient_data' => $patient]);

    return response()->json($patient, 200);  // إرجاع المريض المعدل
}


    // حذف مريض
    public function destroy($id)
    {
        $patient = Patient::find($id);

        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        $patient->delete();  // حذف المريض
        return response()->json(['message' => 'Patient deleted successfully'], 200);  // إرجاع رسالة النجاح
    }
}
