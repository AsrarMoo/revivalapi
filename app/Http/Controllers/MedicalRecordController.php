<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Medication;
use App\Models\Test;
use App\Models\Doctor;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // تأكد من استيراد الفئة Log
use App\Models\MedicalRecordTest;
use App\Models\RecordMedication; // تأكد من استيراد هذا النموذج

class MedicalRecordController extends Controller
{
    public function storeMedicalRecordAndTests(Request $request)
    {
        // التحقق من البيانات المدخلة
        $validatedData = $request->validate([
            'patient_name' => 'required|string',
            'hospital_name' => 'required|string',
            'patient_status' => 'required|string',
            'notes' => 'nullable|string',
            'medications' => 'nullable|array',
            'tests' => 'nullable|array',
            'result_values' => 'nullable|array',
        ]);
    
        // استرجاع بيانات الطبيب من التوكن
        $doctor = auth()->user();
    
        Log::info('🟢 بدء تخزين السجل الطبي', ['request_data' => $validatedData]);
    
        // التحقق مما إذا كان الطبيب مرتبطًا بأي مستشفى
        $doctorHospital = DB::table('hospital_doctors')
            ->where('doctor_id', $doctor->doctor_id)
            ->exists();
    
        if (!$doctorHospital) {
            Log::error('❌ الطبيب غير مرتبط بأي مستشفى', ['doctor_id' => $doctor->doctor_id]);
            return response()->json(['message' => 'عذرًا، لا يمكنك إضافة سجل طبي لأنك غير مضاف لأي مستشفى'], 403);
        }
    
        // استرجاع المريض والمستشفى
        $patient = Patient::where('patient_name', $validatedData['patient_name'])->first();
        $hospital = Hospital::where('hospital_name', $validatedData['hospital_name'])->first();
    
        // التأكد من وجود المريض والمستشفى
        if (!$patient || !$hospital) {
            Log::error('❌ المريض أو المستشفى غير موجود', [
                'patient_name' => $validatedData['patient_name'],
                'hospital_name' => $validatedData['hospital_name']
            ]);
            return response()->json(['message' => 'المريض أو المستشفى غير موجود'], 404);
        }
    
        // إنشاء السجل الطبي
        $medicalRecord = new MedicalRecord();
        $medicalRecord->patient_id = $patient->patient_id;
        $medicalRecord->hospital_id = $hospital->hospital_id;
        $medicalRecord->doctor_id = $doctor->doctor_id;
        $medicalRecord->patient_status = $validatedData['patient_status'];
        $medicalRecord->notes = $validatedData['notes'];
        $medicalRecord->save();
    
        Log::info('✅ تم إنشاء السجل الطبي بنجاح', ['medical_record_id' => $medicalRecord->medical_record_id]);
    
        // إضافة الأدوية إلى السجل الطبي
        if (!empty($validatedData['medications'])) {
            foreach ($validatedData['medications'] as $medication_name) {
                $medication = Medication::where('medication_name', $medication_name)->first();
                if ($medication) {
                    $medicalRecord->medications()->attach($medication->medication_id);
                    Log::info('💊 تم حفظ الدواء بنجاح', [
                        'medication_name' => $medication_name,
                        'medical_record_id' => $medicalRecord->medical_record_id
                    ]);
                } else {
                    Log::warning('⚠️ الدواء غير موجود', ['medication_name' => $medication_name]);
                }
            }
        }
    
        // إضافة الفحوصات ونتائجها للسجل الطبي
        if (!empty($validatedData['tests']) && !empty($validatedData['result_values'])) {
            foreach ($validatedData['tests'] as $index => $test_name) {
                $test = Test::where('test_name', $test_name)->first();
                if ($test) {
                    $medicalRecord->tests()->attach($test->test_id, [
                        'result_value' => $validatedData['result_values'][$index],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
    
                    Log::info('🔬 تم حفظ نتيجة الفحص بنجاح', [
                        'test_name' => $test_name,
                        'result_value' => $validatedData['result_values'][$index],
                        'medical_record_id' => $medicalRecord->medical_record_id
                    ]);
                } else {
                    Log::warning('⚠️ الفحص غير موجود', ['test_name' => $test_name]);
                }
            }
        }
    
        return response()->json([
            'message' => '✅ تم حفظ السجل الطبي ونتائج الفحوصات بنجاح',
            'medical_record_id' => $medicalRecord->medical_record_id
        ], 201);
    }
    


//جلب اسماء المرضى
public function getHospitalPatients()
{
    $user = Auth::user();
    
    if ($user && $user->hospital_id) {
        $hospitalId = $user->hospital_id;

        $patients = MedicalRecord::with('patient')
            ->where('hospital_id', $hospitalId)
            ->get()
            ->unique('patient_id')  // لتجنب التكرار
            ->map(function ($record) {
                return [
                    'patient_id' => $record->patient_id,
                    'patient_name' => $record->patient->patient_name,
                ];
            });

        return response()->json($patients->values());
    }

    return response()->json(['error' => 'Hospital ID not found in token'], 404);
}

//جلب تواريخ السجلات الطبية للمرضى
public function getPatientRecordsDates($patientId)
{
    Log::info("🔍 البحث عن السجلات الطبية للمريض ID: $patientId");

    $dates = MedicalRecord::where('patient_id', $patientId)
        ->select('medical_record_id', 'created_at')
        ->orderBy('created_at', 'desc')
        ->get();

    Log::info("📊 عدد السجلات المسترجعة: " . $dates->count());

    if ($dates->isEmpty()) {
        Log::warning("⚠ لا توجد سجلات للمريض ID: $patientId");
        return response()->json(['message' => 'لم يتم العثور على أي سجلات لهذا المريض'], 404);
    }

    Log::info("✅ السجلات المسترجعة: ", $dates->toArray());

    return response()->json($dates);
}



//جلب سجلات المرضى المستشفى
public function getHospitalRecordDetails($medicalRecordId)
{
    // استخراج المستخدم من التوكن
    $user = Auth::user();

    // التحقق مما إذا كان المستخدم ينتمي إلى مستشفى
    if ($user && $user->hospital_id) {
        $hospitalId = $user->hospital_id;  // معرّف المستشفى من التوكن

        // جلب السجل الطبي المطلوب بناءً على معرفه وتأكد أنه تابع لهذا المستشفى
        $record = MedicalRecord::with([
            'patient', 
            'doctor', 
            'medicalRecordTests.test', 
            'recordMedications.medication'
        ])
        ->where('hospital_id', $hospitalId)
        ->where('medical_record_id', $medicalRecordId)
        ->first(); // جلب سجل واحد فقط

        // ✅ التحقق إذا لم يتم العثور على السجل
        if (!$record) {
            return response()->json(['message' => 'لا يوجد سجل بهذا المعرف'], 404);
        }

        // تجهيز الاستجابة بالبيانات المطلوبة
        return response()->json([
            'patient_name' => $record->patient ? $record->patient->patient_name : null,  // اسم المريض
            'doctor_name' => $record->doctor ? $record->doctor->doctor_name : null,  // اسم الطبيب
            'medical_record_id' => $record->medical_record_id,
            'patient_status' => $record->patient_status,  // حالة المريض
            'notes' => $record->notes,  // الملاحظات
            'created_at' => $record->created_at->toDateTimeString(),  // 🟢 تاريخ الإنشاء
            'updated_at' => $record->updated_at->toDateTimeString(),  // 🔵 تاريخ التحديث
            'tests' => $record->medicalRecordTests->map(function ($test) {
                return [
                    'test_name' => $test->test ? $test->test->test_name : null,  // اسم الفحص
                    'result_value' => $test->result_value,  // نتيجة الفحص
                ];
            }),
            'medications' => $record->recordMedications->map(function ($medication) {
                return [
                    'medication_name' => $medication->medication ? $medication->medication->medication_name : null,  // اسم الدواء
                ];
            }),
        ]);
    }

    // إذا لم يتم العثور على معرّف المستشفى في التوكن
    return response()->json(['error' => 'Hospital ID not found in token'], 404);
}


//جلب سجلات المرضى الخاصة بالطبيب
public function getDoctorPatients()
{
    // استخراج المستخدم (الطبيب) من التوكن
    $user = Auth::user();

    // التحقق إذا كان المستخدم هو طبيب وله معرف خاص به
    if ($user && $user->doctor_id) {
        $doctorId = $user->doctor_id;  // معرّف الطبيب من التوكن

        // جلب السجلات الطبية المتعلقة بالطبيب فقط مع تحميل العلاقة الخاصة بالمريض
        $patients = MedicalRecord::with('patient')
            ->where('doctor_id', $doctorId) // هنا يتم التصفية حسب الطبيب
            ->get()
            ->unique('patient_id')  // لتجنب التكرار
            ->map(function ($record) {
                return [
                    'patient_id' => $record->patient_id,
                    'patient_name' => $record->patient->patient_name, // عرض اسم المريض
                ];
            });

        return response()->json($patients->values());
    }

    // إذا لم يكن هناك معرف للطبيب في التوكن
    return response()->json(['error' => 'Doctor ID not found in token'], 404);
}

//جلب تواريخ السجلات الطبية للمريض الخاصة بالطبيب
public function getDoctorPatientRecordsDates($patientId)
{
    Log::info("🔍 البحث عن السجلات الطبية للمريض ID: $patientId للطبيب");

    // استخراج المستخدم (الطبيب) من التوكن
    $user = Auth::user();

    // التحقق إذا كان المستخدم هو طبيب وله معرف خاص به
    if ($user && $user->doctor_id) {
        $doctorId = $user->doctor_id;  // معرّف الطبيب من التوكن

        // جلب السجلات الطبية المتعلقة بالطبيب
        $dates = MedicalRecord::where('patient_id', $patientId)
            ->where('doctor_id', $doctorId) // تصفية السجلات حسب الطبيب
            ->select('medical_record_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info("📊 عدد السجلات المسترجعة: " . $dates->count());

        if ($dates->isEmpty()) {
            Log::warning("⚠ لا توجد سجلات للمريض ID: $patientId للطبيب ID: $doctorId");
            return response()->json(['message' => 'لم يتم العثور على أي سجلات لهذا المريض للطبيب المحدد'], 404);
        }

        Log::info("✅ السجلات المسترجعة: ", $dates->toArray());

        return response()->json($dates);
    }

    // إذا لم يكن هناك معرف للطبيب في التوكن
    return response()->json(['error' => 'Doctor ID not found in token'], 404);
}


//جلب التفاصيل السجل   للمريض الخاص بالطبيب
public function getDoctorRecordDetails($medicalRecordId)
{
    // استخراج المستخدم (الطبيب) من التوكن
    $user = Auth::user();

    // التحقق مما إذا كان المستخدم هو طبيب وله معرف خاص به
    if ($user && $user->doctor_id) {
        $doctorId = $user->doctor_id;  // معرّف الطبيب من التوكن

        // جلب السجل الطبي المطلوب بناءً على معرفه والتأكد من أنه تابع للطبيب
        $record = MedicalRecord::with([
            'patient', 
            'hospital', 
            'medicalRecordTests.test', 
            'recordMedications.medication'
        ])
        ->where('doctor_id', $doctorId)  // تصفية السجلات حسب الطبيب
        ->where('medical_record_id', $medicalRecordId)  // جلب السجل بناءً على المعرف
        ->first(); // جلب سجل واحد فقط

        // ✅ التحقق إذا لم يتم العثور على السجل
        if (!$record) {
            return response()->json(['message' => 'لا يوجد سجل بهذا المعرف للطبيب المحدد'], 404);
        }

        // تجهيز الاستجابة بالبيانات المطلوبة
        return response()->json([
            'patient_name' => $record->patient ? $record->patient->patient_name : null,  // اسم المريض
            'hospital_name' => $record->hospital ? $record->hospital->hospital_name : null,  // اسم الطبيب
            'medical_record_id' => $record->medical_record_id,
            'patient_status' => $record->patient_status,  // حالة المريض
            'notes' => $record->notes,  // الملاحظات
            'created_at' => $record->created_at->toDateTimeString(),  // 🟢 تاريخ الإنشاء
            'updated_at' => $record->updated_at->toDateTimeString(),  // 🔵 تاريخ التحديث
            'tests' => $record->medicalRecordTests->map(function ($test) {
                return [
                    'test_name' => $test->test ? $test->test->test_name : null,  // اسم الفحص
                    'result_value' => $test->result_value,  // نتيجة الفحص
                ];
            }),
            'medications' => $record->recordMedications->map(function ($medication) {
                return [
                    'medication_name' => $medication->medication ? $medication->medication->medication_name : null,  // اسم الدواء
                ];
            }),
        ]);
    }

    // إذا لم يكن هناك معرف للطبيب في التوكن
    return response()->json(['error' => 'Doctor ID not found in token'], 404);
}

}