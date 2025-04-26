<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HospitalDoctorRequest;
use App\Models\HospitalDoctor; // ✅ استدعاء الموديل الصحيح
use App\Models\Notification;
use App\Models\User;
use App\Models\Hospital;
use App\Models\Doctor;

class HospitalDoctorRequestController extends Controller
{
    // تقديم طلب إضافة طبيب من قبل المستشفى
    public function requestDoctor(Request $request)
    {
        // استخراج hospital_id من المستخدم المصادق عليه
        $hospital_id = Auth::user()->hospital_id;

        // التحقق من أن المستخدم هو مستشفى
        if (!$hospital_id) {
            return response()->json(['error' => 'غير مصرح لك بإرسال الطلب، يجب أن تكون مستشفى.'], 403);
        }

        // التحقق من صحة البيانات
        $request->validate([
            'doctor_id' => 'required|exists:doctors,doctor_id',
        ]);

        // التحقق من وجود طلب سابق لهذا الطبيب
        $existingRequest = HospitalDoctorRequest::where([
            ['hospital_id', $hospital_id],
            ['doctor_id', $request->doctor_id]
        ])->exists();

        if ($existingRequest) {
            return response()->json(['message' => 'تم إرسال طلب مسبق لهذا الطبيب!'], 400);
        }

        // جلب بيانات المستشفى والطبيب
        $hospital = Hospital::findOrFail($hospital_id);
        $doctor = Doctor::findOrFail($request->doctor_id);

        // إنشاء الطلب
        $requestData = HospitalDoctorRequest::create([
            'hospital_id' => $hospital_id,
            'doctor_id' => $request->doctor_id,
            'status' => 'معلق',
        ]);

        // 🔹 إرسال إشعار تلقائي إلى وزارة الصحة (المستخدمين من نوع healthMinistry)
        $admins = User::where('user_type', 'healthMinistry')->pluck('user_id');

        if ($admins->isNotEmpty()) {
            $notifications = [];

            foreach ($admins as $admin_id) {
                $notifications[] = [
                    'user_id' => $admin_id,
                    'created_by' => Auth::id(), // 🔹 استخدام user_id للمستشفى
                    'title' => 'طلب جديد لإضافة طبيب',
                    'message' => "قام المستشفى ({$hospital->hospital_name}) بإرسال طلب لإضافة الطبيب ({$doctor->doctor_name}).",
                    'type' => 'adding',
                    'created_at' => now(),
                ];
            }

            Notification::insert($notifications);
        }

        return response()->json([
            'message' => 'تم إرسال الطلب بنجاح!',
            'data' => [
                'request_id' => $requestData->request_id,
                'hospital_id' => $hospital->hospital_id,
                'hospital_name' => $hospital->hospital_name,
                'doctor_id' => $doctor->doctor_id,
                'doctor_name' => $doctor->doctor_name,
                'status' => $requestData->status,
            ]
        ], 201);
    }

    // 🔹 جلب اسم الطبيب واسم المستشفى لكل طلب معلق
    public function getDoctorHospitalRequests()
    {
        $requests = \DB::table('hospital_doctors')
            ->join('hospitals', 'hospital_doctors.hospital_id', '=', 'hospitals.hospital_id')
            ->join('doctors', 'hospital_doctors.doctor_id', '=', 'doctors.doctor_id')
            ->select(
                'hospital_doctors.id as request_id',
                'hospitals.hospital_id',
                'hospitals.hospital_name',
                'doctors.doctor_id',
                'doctors.doctor_name',
                'hospital_doctors.assigned_at'
            )
            ->get();
    
        if ($requests->isEmpty()) {
            return response()->json(['message' => 'لا توجد طلبات متاحة.'], 200);
        }
    
        return response()->json($requests, 200);
    }
    
    // 🔹 جلب أسماء المستشفيات التي يعمل فيها الطبيب
    public function getDoctorHospitals()
    {
        $doctor_id = Auth::user()->doctor_id;

        if (!$doctor_id) {
            return response()->json(['error' => 'غير مصرح لك بالوصول إلى هذه البيانات.'], 403);
        }

        $hospitals = HospitalDoctor::where('doctor_id', $doctor_id)
            ->join('hospitals', 'hospitals.hospital_id', '=', 'hospital_doctors.hospital_id')
            ->select('hospitals.hospital_id', 'hospitals.hospital_name')
            ->get();

        if ($hospitals->isEmpty()) {
            return response()->json(['message' => 'هذا الطبيب لا يعمل في أي مستشفى.'], 200);
        }

        return response()->json($hospitals, 200);
    }
}
