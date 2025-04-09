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
use Carbon\Carbon;

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
    

// جلب جميع السجلات الطبية للمرضى التي تم علاجهم في المستشفى الحالي
public function getHospitalPatients()
{
    $user = Auth::user();

    if ($user && $user->hospital_id) {
        $hospitalId = $user->hospital_id;

        // جلب جميع السجلات الطبية للمرضى الذين تم علاجهم في المستشفى الحالي
        $patients = MedicalRecord::with('patient')
            ->where('hospital_id', $hospitalId)  // جلب السجلات التي تخص المستشفى الحالي
            ->orWhereHas('patient', function ($query) use ($hospitalId) {
                $query->whereHas('medicalRecords', function ($q) use ($hospitalId) {
                    $q->where('hospital_id', $hospitalId);  // التأكد من أن المريض قد تعالج في هذا المستشفى سابقًا
                });
            })
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

// جلب تواريخ السجلات الطبية لجميع المرضى الذين قد تعالجوا في المستشفى الحالي
public function getPatientRecordsDates($patientId)
{
    Log::info("🔍 البحث عن السجلات الطبية للمريض ID: $patientId");

    $user = Auth::user();

    if ($user && $user->hospital_id) {
        $hospitalId = $user->hospital_id;  // معرّف المستشفى من التوكن

        // جلب جميع السجلات الطبية للمريض الذي تم علاجه في المستشفى الحالي
        $dates = MedicalRecord::where('patient_id', $patientId)
            ->where(function ($query) use ($hospitalId) {
                $query->where('hospital_id', $hospitalId)  // جلب السجلات الخاصة بالمستشفى الحالي
                    ->orWhereHas('patient.medicalRecords', function ($q) use ($hospitalId) {
                        $q->where('hospital_id', $hospitalId);  // التأكد أن المريض قد تعالج في هذا المستشفى من قبل
                    });
            })
            ->select('medical_record_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info("📊 عدد السجلات المسترجعة: " . $dates->count());

        if ($dates->isEmpty()) {
            Log::warning("⚠ لا توجد سجلات للمريض ID: $patientId في المستشفى ID: $hospitalId");
            return response()->json(['message' => 'لم يتم العثور على أي سجلات لهذا المريض في المستشفى المحدد'], 404);
        }

        Log::info("✅ السجلات المسترجعة: ", $dates->toArray());

        return response()->json($dates);
    }

    return response()->json(['error' => 'Hospital ID not found in token'], 404);
}

// جلب تفاصيل السجلات الطبية للمريض في المستشفى الحالي
public function getHospitalRecordDetails($medicalRecordId)
{
    // استخراج المستخدم من التوكن
    $user = Auth::user();

    // التحقق مما إذا كان المستخدم ينتمي إلى مستشفى
    if ($user && $user->hospital_id) {
        $hospitalId = $user->hospital_id;  // معرّف المستشفى من التوكن

        // جلب السجل الطبي المطلوب بناءً على معرفه وتأكد أنه تابع لهذا المستشفى أو تم علاجه في هذا المستشفى
        $record = MedicalRecord::with([
            'patient',
            'doctor',
            'medicalRecordTests.test',
            'recordMedications.medication'
        ])
        ->where(function ($query) use ($hospitalId, $medicalRecordId) {
            $query->where('hospital_id', $hospitalId)  // جلب السجل إذا كان خاص بالمستشفى الحالي
                  ->orWhereHas('patient.medicalRecords', function ($q) use ($hospitalId) {
                      $q->where('hospital_id', $hospitalId);  // التأكد من أن المريض قد تعالج في هذا المستشفى سابقًا
                  });
        })
        ->where('medical_record_id', $medicalRecordId)
        ->first(); // جلب السجل بناءً على المعرف

        // ✅ التحقق إذا لم يتم العثور على السجل
        if (!$record) {
            return response()->json(['message' => 'لا يوجد سجل بهذا المعرف في المستشفى المحدد أو المريض لم يتعالج هنا من قبل'], 404);
        }

        // تجهيز الاستجابة بالبيانات المطلوبة
        return response()->json([
            'patient_name' => $record->patient ? $record->patient->patient_name : null,  // اسم المريض
            'doctor_name' => $record->doctor ? $record->doctor->doctor_name : null,  // اسم الطبيب
            'hospital_name' => $record->hospital ? $record->hospital->hospital_name : null,  // اسم الطبيب
            'medical_record_id' => $record->medical_record_id,
            'patient_status' => $record->patient_status,  // حالة المريض
            'notes' => $record->notes,  // الملاحظات
            
            'created_at' => Carbon::parse($record->created_at)->format('d-m-Y h:i:s A'),
            'updated_at' => Carbon::parse($record->updated_at)->format('d-m-Y h:i:s A'),
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

 // جلب سجلات المرضى الخاصة بالطبيب
 public function getDoctorPatients()
 {
     // استخراج المستخدم (الطبيب) من التوكن
     $user = Auth::user();

     // التحقق إذا كان المستخدم هو طبيب وله معرف خاص به
     if ($user && $user->doctor_id) {
         $doctorId = $user->doctor_id;  // معرّف الطبيب من التوكن

         // جلب السجلات الطبية المتعلقة بالطبيب مع تحميل العلاقة الخاصة بالمريض
         $patients = MedicalRecord::with('patient')
             ->where('doctor_id', $doctorId)  // هنا يتم التصفية حسب الطبيب
             ->get()
             ->unique('patient_id')  // لتجنب التكرار
             ->map(function ($record) {
                 return [
                     'patient_id' => $record->patient_id,
                     'patient_name' => $record->patient->patient_name,  // عرض اسم المريض
                 ];
             });

         return response()->json($patients->values());
     }

     // إذا لم يكن هناك معرف للطبيب في التوكن
     return response()->json(['error' => 'Doctor ID not found in token'], 404);
 }

 // جلب تواريخ السجلات الطبية للمريض الخاصة بالطبيب
 public function getDoctorPatientRecordsDates($patientId)
 {
     Log::info("🔍 البحث عن السجلات الطبية للمريض ID: $patientId للطبيب");

     // استخراج المستخدم (الطبيب) من التوكن
     $user = Auth::user();

     // التحقق إذا كان المستخدم هو طبيب وله معرف خاص به
     if ($user && $user->doctor_id) {
         $doctorId = $user->doctor_id;  // معرّف الطبيب من التوكن

         // التحقق إذا كان المريض قد تعالج عند هذا الطبيب
         $patientRecords = MedicalRecord::where('patient_id', $patientId)
             ->where('doctor_id', $doctorId) // تصفية السجلات حسب الطبيب
             ->get();

         // إذا كان المريض قد تعالج عند الطبيب، نقوم بجلب جميع سجلاته
         if ($patientRecords->isNotEmpty()) {
             $dates = MedicalRecord::where('patient_id', $patientId)
                 ->select('medical_record_id', 'created_at')
                 ->orderBy('created_at', 'desc')
                 ->get();
         } else {
             // إذا لم يكن قد تعالج عند الطبيب هذا فقط نظهر السجلات الخاصة بهذا الطبيب
             $dates = MedicalRecord::where('patient_id', $patientId)
                 ->where('doctor_id', $doctorId)
                 ->select('medical_record_id', 'created_at')
                 ->orderBy('created_at', 'desc')
                 ->get();
         }

         Log::info("📊 عدد السجلات المسترجعة: " . $dates->count());

         if ($dates->isEmpty()) {
             Log::warning("⚠ لا توجد سجلات للمريض ID: $patientId للطبيب ID: $doctorId");
             return response()->json(['message' => 'لم يتم العثور على أي سجلات لهذا المريض للطبيب المحدد'], 404);
         }

         // تحويل التواريخ إلى صيغة مفهومة للمستخدم
         $formattedDates = $dates->map(function ($date) {
             return [
                 'medical_record_id' => $date->medical_record_id,
                 'created_at'=>   Carbon::parse($date->created_at)->format('d-m-Y h:i:s A'),
              //   'created_at' => $date->created_at->format('d-m-Y H:i'), // تنسيق التاريخ بشكل مفهوم
             ];
         });

         Log::info("✅ السجلات المسترجعة: ", $formattedDates->toArray());

         return response()->json($formattedDates);
     }

     // إذا لم يكن هناك معرف للطبيب في التوكن
     return response()->json(['error' => 'Doctor ID not found in token'], 404);
 }

 public function getDoctorRecordDetails($medicalRecordId)
{
    // استخراج المستخدم (الطبيب) من التوكن
    $user = Auth::user();

    // التحقق مما إذا كان المستخدم هو طبيب وله معرف خاص به
    if ($user && $user->doctor_id) {
        $doctorId = $user->doctor_id;  // معرّف الطبيب من التوكن

        // جلب السجل الطبي المطلوب بناءً على معرفه
        $record = MedicalRecord::with([
            'patient', 
            'hospital', 
            'doctor',
            'medicalRecordTests.test', 
            'recordMedications.medication'
        ])
        // نبحث عن السجل بناءً على المعرف فقط، بغض النظر عن الطبيب
        ->where('medical_record_id', $medicalRecordId)  
        ->first(); // جلب سجل واحد فقط

        // ✅ التحقق إذا لم يتم العثور على السجل
        if (!$record) {
            return response()->json(['message' => 'لا يوجد سجل بهذا المعرف للطبيب المحدد'], 404);
        }

        // التحقق إذا كان الطبيب قد تعامل مع هذا المريض سابقًا
        if ($record->doctor_id !== $doctorId) {
            // إذا لم يكن الطبيب الحالي هو من قام بفتح السجل، نقوم بالتحقق من وجود سجل له سابقًا
            $hasSeenPatientBefore = MedicalRecord::where('patient_id', $record->patient_id)
                ->where('doctor_id', $doctorId)
                ->exists();

            // إذا كان قد عالج المريض سابقًا، يسمح له بالوصول إلى السجل
            if (!$hasSeenPatientBefore) {
                return response()->json(['message' => 'لا يمكنك الوصول إلى هذا السجل لأنك لم تعالج المريض من قبل.'], 403);
            }
        }

        // تجهيز الاستجابة بالبيانات المطلوبة مع تنسيق التواريخ
        return response()->json([
            'patient_name' => $record->patient ? $record->patient->patient_name : null,  // اسم المريض
            'hospital_name' => $record->hospital ? $record->hospital->hospital_name : null,  // اسم المستشفى
            'doctor_name' => $record->doctor ? $record->doctor->doctor_name : null,  // اسم الطبيب
            'medical_record_id' => $record->medical_record_id,
            'patient_status' => $record->patient_status,  // حالة المريض
            'notes' => $record->notes,  // الملاحظات
            'created_at' =>Carbon::parse($record->created_at)->format('d-m-Y h:i:s A'),
            'updated_at' => Carbon::parse($record->updated_at)->format('d-m-Y h:i:s A'), // 🔵 تاريخ التحديث بتنسيق مفهم
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



// جلب تواريخ السجلات الطبية الخاصة بالمريض المسجل دخوله
public function getPatientRecordsDatesforpatient()
{
    // استخراج المريض من التوكن
    $user = Auth::user();

    if ($user && $user->patient_id) {
        $patientId = $user->patient_id;

        // جلب تواريخ السجلات الطبية الخاصة بالمريض
        $dates = MedicalRecord::where('patient_id', $patientId)
            ->select('medical_record_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // التحقق إذا كانت السجلات فارغة
        if ($dates->isEmpty()) {
            return response()->json(['message' => 'لم يتم العثور على أي سجلات لهذا المريض'], 404);
        }

        // إرجاع التواريخ
        return response()->json($dates);
    }

    // إذا لم يتم العثور على معرّف المريض في التوكن
    return response()->json(['error' => 'Patient ID not found in token'], 404);
}

// جلب تفاصيل السجل الطبي للمريض بناءً على تاريخ معين
public function getPatientRecordDetailsforpatient($medicalRecordId)
{
    // استخراج المريض من التوكن
    $user = Auth::user();

    if ($user && $user->patient_id) {
        $patientId = $user->patient_id;

        // جلب السجل الطبي بناءً على معرفه
        $record = MedicalRecord::with([
            'doctor', 
            'medicalRecordTests.test', 
            'recordMedications.medication'
        ])
        ->where('patient_id', $patientId)  // التحقق أن السجل يخص المريض المسجل دخوله
        ->where('medical_record_id', $medicalRecordId)
        ->first(); // جلب السجل بناءً على المعرف

        // ✅ التحقق إذا لم يتم العثور على السجل
        if (!$record) {
            return response()->json(['message' => 'لا يوجد سجل بهذا المعرف للمريض'], 404);
        }

        // تجهيز الاستجابة بالبيانات المطلوبة
        return response()->json([
            'doctor_name' => $record->doctor ? $record->doctor->doctor_name : null,  // اسم الطبيب
            'hospital_name' => $record->hospital ? $record->hospital->hospital_name : null,  // اسم المستشفى
           
            'medical_record_id' => $record->medical_record_id,
            'patient_status' => $record->patient_status,  // حالة المريض
            'notes' => $record->notes,  // الملاحظات
            'created_at' => Carbon::parse($record->created_at)->format('d-m-Y h:i:s A'), // 🟢 تاريخ الإنشاء
            'updated_at' => Carbon::parse($record->updated_at)->format('d-m-Y h:i:s A'),// 🔵 تاريخ التحديث
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

    // إذا لم يتم العثور على معرّف المريض في التوكن
    return response()->json(['error' => 'Patient ID not found in token'], 404);
}

}