<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DoctorController extends Controller
{
    // إضافة طبيب جديد
    public function store(Request $request)
    {
        try {
            // التحقق من صحة البيانات
            $request->validate([
                'doctor_name' => 'required|string',
                'doctor_gender' => 'required|in:Male,Female',
                'doctor_address' => 'nullable|string',
                'doctor_image' => 'nullable|image',
                'specialty_id' => 'nullable|integer',
                'doctor_qualification' => 'required|string',
                'doctor_phone' => 'required|string|unique:doctors,doctor_phone',
                'doctor_email' => 'required|email|unique:doctors,doctor_email',
                'password' => 'required|string|min:8',
            ]);

            // رفع الصورة إذا كانت موجودة
            if ($request->hasFile('doctor_image')) {
                $imagePath = $request->file('doctor_image')->store('doctor_images', 'public');
            } else {
                $imagePath = null;
            }

            // إنشاء حساب المستخدم
            $user = User::create([
                'name' => $request->doctor_name,
                'password' => Hash::make($request->password),
                'user_type' => 'doctor',
                'is_active' => true,
            ]);

            // إنشاء سجل الطبيب
            $doctor = Doctor::create([
                'doctor_name' => $request->doctor_name,
                'doctor_gender' => $request->doctor_gender,
                'doctor_address' => $request->doctor_address,
                'doctor_image' => $imagePath,
                'specialty_id' => $request->specialty_id,
                'user_id' => $user->id,
                'doctor_qualification' => $request->doctor_qualification,
                'doctor_phone' => $request->doctor_phone,
                'doctor_email' => $request->doctor_email,
            ]);

            return response()->json($doctor, 201);
        } catch (\Exception $e) {
            Log::error('Error creating doctor:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create doctor', 'message' => $e->getMessage()], 500);
        }
    }

    // استعلام عن جميع الأطباء
    public function index()
    {
        $doctors = Doctor::all();
        return response()->json($doctors, 200);
    }

    // استعلام عن طبيب محدد
    public function show($id)
    {
        $doctor = Doctor::find($id);
        if (!$doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }
        return response()->json($doctor, 200);
    }

    // تحديث بيانات طبيب
    public function update(Request $request, $id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }

        $request->validate([
            'doctor_name' => 'sometimes|required|string',
            'doctor_gender' => 'sometimes|required|in:Male,Female',
            'doctor_address' => 'nullable|string',
            'doctor_image' => 'nullable|image',
            'specialty_id' => 'nullable|integer',
            'doctor_qualification' => 'sometimes|required|string',
            'doctor_phone' => 'sometimes|required|string|unique:doctors,doctor_phone,' . $id . ',doctor_id',
            'doctor_email' => 'sometimes|required|email|unique:doctors,doctor_email,' . $id . ',doctor_id',
        ]);

        if ($request->hasFile('doctor_image')) {
            $imagePath = $request->file('doctor_image')->store('doctor_images', 'public');
            $doctor->doctor_image = $imagePath;
        }

        $doctor->update($request->only([
            'doctor_name', 'doctor_gender', 'doctor_address', 'specialty_id',
            'doctor_qualification', 'doctor_phone', 'doctor_email'
        ]));

        $user = $doctor->user;
        if ($user) {
            $user->update(['name' => $doctor->doctor_name]);
        }

        return response()->json($doctor, 200);
    }

    // حذف طبيب
    public function destroy($id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }

        $doctor->delete();
        if ($doctor->user) {
            $doctor->user->delete();
        }

        return response()->json(['message' => 'Doctor deleted successfully'], 200);
    }
}
