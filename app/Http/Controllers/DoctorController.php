<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DoctorController extends Controller
{
    /**
     * إرجاع قائمة بجميع الأطباء.
     */
    public function index()
    {
        return response()->json(Doctor::all(), 200);
    }

    /**
     * إرجاع بيانات طبيب معين.
     */
    public function show($id)
    {
        $doctor = Doctor::findOrFail($id);
        return response()->json($doctor, 200);
    }

    /**
     * إضافة طبيب جديد وإنشاء حساب مستخدم مرتبط به.
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_name' => 'required|string|max:255',
            'specialty_id' => 'required|integer',
            'doctor_qualification' => 'required|string|max:255',
            'doctor_experience' => 'required|integer',
            'doctor_phone' => 'required|string|max:15|unique:doctors,doctor_phone',
            'doctor_bio' => 'nullable|string',
            'doctor_image' => 'nullable|string',
            'doctor_gender' => 'required|in:Male,Female',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6'
        ]);
    
        // إنشاء مستخدم جديد للطبيب
        $user = User::create([
            'name' => $request->doctor_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'doctor', // يتم تعيين النوع تلقائيًا
            'is_active' => 1
        ]);
    
        // إنشاء الطبيب وربطه بالمستخدم
        $doctor = Doctor::create([
            'doctor_name' => $request->doctor_name,
            'specialty_id' => $request->specialty_id,
            'doctor_qualification' => $request->doctor_qualification,
            'doctor_experience' => $request->doctor_experience,
            'doctor_phone' => $request->doctor_phone,
            'doctor_bio' => $request->doctor_bio,
            'doctor_image' => $request->doctor_image,
            'doctor_gender' => $request->doctor_gender,
            'user_id' => $user->user_id
        ]);
    
        // 🔹 **تحديث جدول `users` لإضافة `doctor_id` للطبيب**
        $user->doctor_id = $doctor->doctor_id;
        $user->save();
    
        return response()->json(['message' => 'Doctor added successfully', 'doctor' => $doctor, 'user' => $user], 201);
    }
    

    /**
     * تحديث بيانات الطبيب والمستخدم المرتبط به.
     */
    public function update(Request $request, $id)
    {
        $doctor = Doctor::findOrFail($id);
        $user = User::findOrFail($doctor->user_id);

        $request->validate([
            'doctor_name' => 'sometimes|string|max:255',
            'specialty_id' => 'sometimes|integer',
            'doctor_qualification' => 'sometimes|string|max:255',
            'doctor_experience' => 'sometimes|integer',
            'doctor_phone' => "sometimes|string|max:15|unique:doctors,doctor_phone,{$id},doctor_id",
            'doctor_bio' => 'nullable|string',
            'doctor_image' => 'nullable|string',
            'doctor_gender' => 'sometimes|in:Male,Female',
            'email' => "sometimes|string|email|max:255|unique:users,email,{$user->user_id},user_id",
            'password' => 'sometimes|string|min:6'
        ]);

        $doctor->update($request->all());

        // تحديث بيانات المستخدم المرتبط
        if ($request->has('doctor_name') || $request->has('email') || $request->has('password')) {
            $user->update([
                'name' => $request->doctor_name ?? $user->name,
                'email' => $request->email ?? $user->email,
                'password' => $request->password ? Hash::make($request->password) : $user->password,
            ]);
        }

        return response()->json(['message' => 'Doctor updated successfully', 'doctor' => $doctor], 200);
    }

    /**
     * حذف طبيب وحذف المستخدم المرتبط به.
     */
    public function destroy($id)
    {
        $doctor = Doctor::findOrFail($id);

        // حذف المستخدم المرتبط
        User::where('user_id', $doctor->user_id)->delete();

        // حذف الطبيب
        $doctor->delete();

        return response()->json(['message' => 'Doctor deleted successfully'], 200);
    }
}
