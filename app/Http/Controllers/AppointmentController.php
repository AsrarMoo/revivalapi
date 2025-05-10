<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Notification;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Schedule;
use App\Models\Patient;
use App\Models\Test;
use App\Models\Medication;
use App\Models\MedicationRecord;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index()
    {
        return response()->json(Appointment::with(['patient', 'hospital', 'doctor', 'schedule'])->get());
    }

    public function store(Request $request)
    {
        // 🔹 جلب بيانات المستخدم من التوكن
        $user = auth()->user(); // يقوم جلب بيانات المستخدم بناءً على التوكن
    
        // التأكد من أن المستخدم هو مريض وأن لديه patient_id
        $patient_id = $user->patient_id;
    
        // التحقق من صحة المدخلات
        $validatedData = $request->validate([
            'doctor_name' => 'required|string',
            'hospital_name' => 'required|string',
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]);
    
        // البحث عن الطبيب بناءً على الاسم
        $doctor = Doctor::where('doctor_name', $validatedData['doctor_name'])->first();
        if (!$doctor) {
            return response()->json(['error' => 'اسم الطبيب غير موجود.'], 404);
        }
    
        // البحث عن المستشفى بناءً على الاسم
        $hospital = Hospital::where('hospital_name', $validatedData['hospital_name'])->first();
        if (!$hospital) {
            return response()->json(['error' => 'اسم المستشفى غير موجود.'], 404);
        }
    
        // البحث عن الموعد المناسب في جدول مواعيد الطبيب
        $schedule = Schedule::where('doctor_id', $doctor->doctor_id)
                            ->where('hospital_id', $hospital->hospital_id)
                            ->where('day_of_week', $validatedData['day_of_week'])
                            ->where('start_time', $validatedData['start_time'])
                            ->where('end_time', $validatedData['end_time'])
                            ->first();
    
        if (!$schedule) {
            return response()->json(['error' => 'لا يوجد موعد مطابق لهذا اليوم والوقت.'], 404);
        }
    
        // إنشاء الموعد الجديد في حالة "معلقة" (Pending)
        $appointment = new Appointment();
        $appointment->doctor_id = $doctor->doctor_id;
        $appointment->hospital_id = $hospital->hospital_id;
        $appointment->patient_id = $patient_id; // ترحيل معرف المريض
        $appointment->schedule_id = $schedule->schedule_id;
        $appointment->status = 'Pending'; // حالة الموعد تكون "معلقة" في البداية
        $appointment->save();
    
        // 🔹 إرسال إشعار إلى المستشفى لتأكيد الموعد
        $this->sendNotificationToHospital($hospital, $appointment);
    
        return response()->json([
            'message' => 'تم إضافة الموعد بنجاح. بانتظار تأكيد المستشفى.',
            'data' => $appointment
        ], 201);
    }
    
    private function sendNotificationToHospital($hospital, $appointment)
    {
        // إرسال إشعار إلى المستشفى باستخدام جدول الإشعارات
    
        $notification = new Notification();
        $notification->user_id = $hospital->user_id; // معرف المستخدم الخاص بالمستشفى
        $notification->created_by = auth()->user()->patient_id; // يتم وضع معرف المريض الذي أضاف الموعد
        $notification->title = "موعد جديد منتظر تأكيدك";
        $notification->message = "تم طلب حجز جديد مع الطبيب " . $appointment->doctor->doctor_name . " من قبل المريض " . $appointment->patient->patient_name . " وينتظر تأكيدك.";
        $notification->type = 'booking'; // نوع الإشعار (حجز)
        $notification->is_read = 0; // إشعار غير مقروء
        $notification->request_id = $appointment->appointment_id;
        $notification->save();
    }


    public function confirmAppointment($notificationId)
    {
        // البحث عن الإشعار باستخدام معرف الإشعار (notification_id)
        $notification = Notification::find($notificationId);
    
        // التحقق إذا كان الإشعار موجودًا
        if (!$notification) {
            return response()->json(['error' => 'الإشعار غير موجود.'], 404);
        }
    
        // الحصول على معرف الموعد من حقل request_id في الإشعار
        $appointmentId = $notification->request_id;
    
        // البحث عن الموعد باستخدام معرف الموعد
        $appointment = Appointment::find($appointmentId);
    
        // التحقق إذا كان الموعد موجودًا
        if (!$appointment) {
            return response()->json(['error' => 'الموعد غير موجود.'], 404);
        }
    
        // التحقق من حالة الموعد إذا كان في حالة "Pending"
        if ($appointment->status !== 'Pending') {
            return response()->json(['error' => 'الموعد لا يمكن تأكيده لأنه ليس في حالة "Pending".'], 400);
        }
    
        // تحديث حالة الموعد إلى "Confirmed"
        $appointment->status = 'Confirmed';
        $appointment->save();
    
        // إرسال إشعار للمريض
        $this->sendNotificationToPatient($appointment);
    
        // إرسال إشعار للطبيب
        $this->sendNotificationToDoctor($appointment);
    
        return response()->json(['message' => 'تم تأكيد الموعد بنجاح.']);
    }
    
    private function sendNotificationToPatient($appointment)
    {
        // إرسال إشعار للمريض بأن الموعد تم تأكيده
        $patient = Patient::find($appointment->patient_id); // الحصول على المريض باستخدام patient_id
    
        $notification = new Notification();
        $notification->user_id = $patient->user_id;  // تحديد المستخدم (المريض)
        $notification->created_by = auth()->user()->id ?? $appointment->hospital->user_id;  // من قام بإنشاء الإشعار (المستشفى) 
        $notification->title = 'تمت الموافقة على حجزك';
        $notification->message = 'تم تأكيد حجزك مع الطبيب ' . $appointment->doctor->doctor_name . 
        ' في مستشفى ' . $appointment->hospital->hospital_name . 
        '. يمكنك تقييم الطبيب الذي قمت بالحجز لديه خلال 24 ساعة من تاريخ الحجز.';
    
        $notification->type = 'booking';  // نوع الإشعار
        $notification->is_read = 0; // تعيين الإشعار كغير مقروء
        $notification->save();
    }

    
    private function sendNotificationToDoctor($appointment)
    {
        // التأكد من تحميل المريض مع الموعد بشكل مسبق
    // تحميل العلاقات قبل الاستخدام
$appointment = Appointment::with(['patient', 'hospital'])->find($appointment->appointment_id);

$doctor = Doctor::find($appointment->doctor_id);

// إنشاء الإشعار
$notification = new Notification();
$notification->user_id = $doctor->user_id;
$notification->created_by = auth()->user()->id ?? $appointment->hospital->user_id;
$notification->title = 'تم حجز موعد لك';

// التأكد من وجود اسم المريض والمستشفى
$patientName = $appointment->patient?->patient_name ?? 'غير معروف';
$hospitalName = $appointment->hospital?->hospital_name ?? 'مجهول';

// رسالة الإشعار النهائية
$notification->message = 'لقد تم حجز موعد معك من قبل المريض ' . $patientName . ' في مستشفى ' . $hospitalName . '.';

$notification->type = 'booking';
$notification->is_read = 0;
$notification->save();
    }
    
    
    //رفض حجز 
    public function rejectAppointment($notificationId)
    {
        // البحث عن الإشعار باستخدام معرف الإشعار
        $notification = Notification::find($notificationId);
    
        // التحقق إذا كان الإشعار موجودًا
        if (!$notification) {
            return response()->json(['error' => 'الإشعار غير موجود.'], 404);
        }
    
        // الحصول على معرف الموعد من حقل request_id
        $appointmentId = $notification->request_id;
    
        // البحث عن الموعد
        $appointment = Appointment::find($appointmentId);
    
        // التحقق إذا كان الموعد موجودًا
        if (!$appointment) {
            return response()->json(['error' => 'الموعد غير موجود.'], 404);
        }
    
        // التحقق من حالة الموعد إذا كان في حالة "Pending"
        if ($appointment->status !== 'Pending') {
            return response()->json(['error' => 'الموعد لا يمكن رفضه لأنه ليس في حالة "Pending".'], 400);
        }
    
        // تحديث حالة الموعد إلى "Rejected"
        $appointment->status = 'Rejected';
        $appointment->save();
    
        // إرسال إشعار للمريض فقط
        $this->sendRejectionNotificationToPatient($appointment);
    
        return response()->json(['message' => 'تم رفض الموعد بنجاح.']);
    }
    private function sendRejectionNotificationToPatient($appointment)
{
    // الحصول على المريض باستخدام patient_id
    $patient = Patient::find($appointment->patient_id);

    if (!$patient) return; // إذا لم يكن هناك مريض مرتبط بالموعد

    // إنشاء إشعار للمريض
    $notification = new Notification();
    $notification->user_id = $patient->user_id;  // تحديد المستخدم (المريض)
    $notification->created_by = auth()->user()->id ?? $appointment->hospital->user_id;  // من قام بإنشاء الإشعار
    $notification->title = 'تم رفض حجزك';
    $notification->message = 'نأسف، تم رفض طلب حجزك مع الدكتور ' . $appointment->doctor->doctor_name . 
                            ' في مستشفى ' . $appointment->hospital->hospital_name . '. يمكنك إعادة المحاولة لاحقاً.';
    $notification->type = 'booking';  // نوع الإشعار
    $notification->is_read = 0; // تعيين الإشعار كغير مقروء
    $notification->save();
}
public function getHospitalAppointments(Request $request)
{
    // الحصول على بيانات المستخدم
    $user = auth()->user();
    
    // التحقق من المستشفى المرتبطة بالمستخدم
    $hospitalId = $user->hospital_id;
    
    // التحقق إذا كان المستخدم مرتبطًا بمستشفى
    if (!$hospitalId) {
        return response()->json(['error' => 'المستخدم ليس مرتبطًا بأي مستشفى.'], 400);
    }
    
    // البحث عن جميع الحجوزات للمستشفى وحالة الحجز "موافقة"
    $appointments = Appointment::where('hospital_id', $hospitalId)
                               ->where('status', 'Confirmed')  // جلب الحجوزات ذات الحالة "موافقة"
                               ->with([
                                   'patient',
                                   'patient.medicalRecords.recordMedications.medication',  // الأدوية المرتبطة بالسجل
                                   'patient.medicalRecords.medicalRecordTests.test',  // الفحوصات المرتبطة بالسجل
                                   'patient.medicalRecords.hospital',  // المستشفى المرتبطة بالسجل
                                   'schedule',  // ربط الموعد مع جدول مواعيد الطبيب
                                   'doctor' // ربط جدول الأطباء للحصول على اسم الطبيب
                               ])
                               ->get();
    
    // التحقق إذا كانت الحجوزات فارغة
    if ($appointments->isEmpty()) {
        return response()->json(['message' => 'لا توجد حجوزات موافق عليها للمستشفى.'], 404);
    }
    
    // إرجاع الحجوزات مع تفاصيل المرضى والسجلات الطبية والأدوية والفحوصات
    return response()->json([
        'appointments' => $appointments->map(function ($appointment) {
            $schedule = $appointment->schedule;  // جدول مواعيد الطبيب المرتبط بالموعد
            $appointmentStartTime = Carbon::parse($schedule->start_time);
            $appointmentEndTime = Carbon::parse($schedule->end_time);

            // إذا كان المريض لا يملك سجل طبي
            if ($appointment->patient->medicalRecords->isEmpty()) {
                return [
                    'appointment_id' => $appointment->appointment_id,
                    'patient_name' => $appointment->patient->patient_name,
                    'appointment_start_time' => $appointmentStartTime->toTimeString(),
                    'appointment_end_time' => $appointmentEndTime->toTimeString(),
                    'day_of_week' => $schedule->day_of_week, // يوم الأسبوع
                    'status' => $appointment->status, // حالة الموعد
                    'doctor_name' => $appointment->doctor ? $appointment->doctor->doctor_name : 'غير متوفر',  // اسم الطبيب إذا موجود
                    'medical_records' => 'لا يوجد سجل طبي لهذا المريض'  // رسالة عندما لا يوجد سجل طبي
                ];
            }

            // إذا كان المريض يملك سجل طبي، نعرض البيانات كما هو الحال
            $doctorName = $appointment->doctor ? $appointment->doctor->doctor_name : 'غير متوفر';  // اسم الطبيب من جدول الطبيب
            
            return [
                'appointment_id' => $appointment->appointment_id,
                'patient_name' => $appointment->patient->patient_name,
                'appointment_start_time' => $appointmentStartTime->toTimeString(),
                'appointment_end_time' => $appointmentEndTime->toTimeString(),
                'day_of_week' => $schedule->day_of_week, // يوم الأسبوع
                'status' => $appointment->status, // حالة الموعد
                'doctor_name' => $doctorName, // اسم الطبيب

                // تفاصيل السجل الطبي
                'medical_records' => $appointment->patient->medicalRecords->map(function ($record) {
                    return [
                        'medical_record_id' => $record->medical_record_id,
                        'notes' => $record->notes,
                        'created_at' => $record->created_at,
                        
                        // اسم الطبيب إذا كان موجودًا في السجل الطبي
                        'doctor_name' => $record->doctor ? $record->doctor->doctor_name : null,
                
                        // اسم المستشفى
                        'hospital_name' => $record->hospital ? $record->hospital->hospital_name : null,
                
                        // الأدوية المرتبطة بالسجل
                        'medications' => $record->recordMedications->map(function ($rm) {
                            return $rm->medication->medication_name ?? null;
                        })->filter(),
                
                        // الفحوصات المرتبطة بالسجل
                        'tests' => $record->medicalRecordTests->map(function ($test) {
                            return [
                                'test_name' => $test->test->test_name ?? null,
                                'result' => $test->result_value
                            ];
                        })->filter()
                    ];
                })
            ];
        })
    ], 200);
}

public function getDoctorAppointments(Request $request)
{
    // الحصول على بيانات المستخدم
    $user = auth()->user();
    
    // التحقق من الطبيب المرتبط بالمستخدم
    $doctorId = $user->doctor_id;
    
    // التحقق إذا كان المستخدم مرتبطًا بطبيب
    if (!$doctorId) {
        return response()->json(['error' => 'المستخدم ليس مرتبطًا بأي طبيب.'], 400);
    }
    
    // البحث عن جميع الحجوزات للطبيب وحالة الحجز "موافقة"
    $appointments = Appointment::where('doctor_id', $doctorId)
                               ->where('status', 'Confirmed')  // جلب الحجوزات ذات الحالة "موافقة"
                               ->with([
                                   'patient',
                                   'patient.medicalRecords.recordMedications.medication',  // الأدوية المرتبطة بالسجل
                                   'patient.medicalRecords.medicalRecordTests.test',  // الفحوصات المرتبطة بالسجل
                                   'patient.medicalRecords.hospital',  // المستشفى المرتبطة بالسجل
                                   'schedule',  // ربط الموعد مع جدول مواعيد الطبيب
                                   'hospital', // علاقة المستشفى من جدول الحجوزات
                                   'doctor' // إضافة علاقة الطبيب
                               ])
                               ->get();
    
    // التحقق إذا كانت الحجوزات فارغة
    if ($appointments->isEmpty()) {
        return response()->json(['message' => 'لا توجد حجوزات موافق عليها للطبيب.'], 404);
    }
    
    // إرجاع الحجوزات مع تفاصيل المرضى والسجلات الطبية والأدوية والفحوصات
    return response()->json([
        'appointments' => $appointments->map(function ($appointment) {
            $schedule = $appointment->schedule;  // جدول مواعيد الطبيب المرتبط بالموعد
            $appointmentStartTime = Carbon::parse($schedule->start_time);
            $appointmentEndTime = Carbon::parse($schedule->end_time);
        
            // إذا كان المريض ليس لديه سجل طبي
            if ($appointment->patient->medicalRecords->isEmpty()) {
                return [
                    'appointment_id' => $appointment->appointment_id,
                    'patient_name' => $appointment->patient->patient_name,
                    'appointment_start_time' => $appointmentStartTime->toTimeString(),
                    'appointment_end_time' => $appointmentEndTime->toTimeString(),
                    'day_of_week' => $schedule->day_of_week, // يوم الأسبوع
                    'status' => $appointment->status, // حالة الموعد
                    'hospital_name' => $appointment->hospital ? $appointment->hospital->hospital_name : 'لا يوجد مستشفى مرتبط',
                    'medical_records' => 'لا يوجد سجل طبي لهذا المريض'
                ];
            }

            // إذا كان المريض يملك سجل طبي، نعرض البيانات كما هو الحال
            return [
                'appointment_id' => $appointment->appointment_id,
                'patient_name' => $appointment->patient->patient_name,
                'appointment_start_time' => $appointmentStartTime->toTimeString(),
                'appointment_end_time' => $appointmentEndTime->toTimeString(),
                'day_of_week' => $schedule->day_of_week, // يوم الأسبوع
                'status' => $appointment->status, // حالة الموعد
                'hospital_name' => $appointment->hospital ? $appointment->hospital->hospital_name : 'لا يوجد مستشفى مرتبط',
                'medical_records' => $appointment->patient->medicalRecords->map(function ($record) use ($appointment) {
                    return [
                        'medical_record_id' => $record->medical_record_id,
                        'notes' => $record->notes,
                        'created_at' => Carbon::parse($record->created_at)->translatedFormat('j F Y، h:i A'),
                        'hospital_name' => $record->hospital ? $record->hospital->hospital_name : null,
                        
                        // إضافة اسم الطبيب في السجل الطبي
                        'doctor_name' => $appointment->doctor ? $appointment->doctor->doctor_name : 'لا يوجد طبيب مرتبط',
                
                        // الأدوية المرتبطة بالسجل
                        'medications' => $record->recordMedications->map(function ($rm) {
                            return $rm->medication->medication_name ?? null;
                        })->filter(),
                
                        // الفحوصات المرتبطة بالسجل
                        'tests' => $record->medicalRecordTests->map(function ($test) {
                            return [
                                'test_name' => $test->test->test_name ?? null,
                                'result' => $test->result_value
                            ];
                        })->filter()
                    ];
                })
            ];
        })
    ], 200);
}

public function cancelAppointment(Request $request, $appointmentId)
{
    // الحصول على بيانات المستخدم
    $user = auth()->user();

    // التحقق من المريض المرتبط بالمستخدم
    $patientId = $user->patient_id;

    // التحقق إذا كان المريض مرتبطًا بحجز
    $appointment = Appointment::where('appointment_id', $appointmentId)
                              ->where('patient_id', $patientId)
                              ->first();

    if (!$appointment) {
        return response()->json(['error' => 'الحجز غير موجود أو ليس للمريض.'], 400);
    }

    // تحديث حالة الحجز إلى "مُلغي"
    $appointment->status = 'Cancelled';
    $appointment->save();

    // إذا أردت حذف الحجز نهائيًا، استخدم:
    // $appointment->delete();

    return response()->json(['message' => 'تم إلغاء الحجز بنجاح.'], 200);
}

public function getPatientAppointments()
{
    // جلب المستخدم الحالي
    $user = auth()->user();

    // التأكد من أن المستخدم لديه patient_id
    if (!$user->patient_id) {
        return response()->json(['error' => 'المستخدم ليس مريضاً.'], 403);
    }

    // جلب حجوزات المريض
    $appointments = Appointment::where('patient_id', $user->patient_id)
                                ->with(['doctor', 'hospital', 'schedule'])
                                ->get();

    if ($appointments->isEmpty()) {
        return response()->json(['message' => 'لا توجد حجوزات لهذا المريض.'], 404);
    }

    // إرجاع بيانات الحجز
    return response()->json([
        'appointments' => $appointments->map(function ($appointment) {
            return [
                'appointment_id' => $appointment->appointment_id,
                'doctor_name' => $appointment->doctor->doctor_name ?? null,
                'hospital_name' => $appointment->hospital->hospital_name ?? null,
                'day_of_week' => $appointment->schedule->day_of_week ?? null,
                'start_time' => Carbon::parse($appointment->schedule->start_time)->translatedFormat('g:i A') ?? null,
                'end_time' => Carbon::parse($appointment->schedule->end_time)->translatedFormat('g:i A') ?? null,
                'status' => $appointment->status,
                'created_at' => Carbon::parse($appointment->created_at)->translatedFormat('l j F Y - g:i A'),
            ];
        }),
    ], 200);
}
public function completeAppointment($appointmentId)
{
    // البحث عن الموعد باستخدام المعرف
    $appointment = Appointment::find($appointmentId);

    // التحقق إذا كان الموعد موجودًا
    if (!$appointment) {
        return response()->json(['error' => 'الموعد غير موجود.'], 404);
    }

    // التحقق من حالة الموعد إذا كانت "Confirmed"
    if ($appointment->status !== 'Confirmed') {
        return response()->json(['error' => 'لا يمكن إكمال الموعد إلا إذا كان في حالة "موافقة".'], 400);
    }

    // تحديث الحالة إلى "Completed"
    $appointment->status = 'Completed';
    $appointment->save();

    return response()->json(['message' => 'تم تحويل الموعد إلى مكتمل بنجاح.']);

}


}
 
