<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HospitalController extends Controller
{
    /**
     * إرجاع قائمة بجميع المستشفيات.
     */
    public function index()
    {
        return response()->json(Hospital::all(), 200);
    }

    /**
     * إرجاع بيانات مستشفى معين.
     */
    public function show($id)
    {
        $hospital = Hospital::find($id);

        if (!$hospital) {
            return response()->json(['message' => 'Hospital not found'], 404);
        }

        return response()->json($hospital, 200);
    }

    /**
     * إضافة مستشفى جديد وإنشاء حساب مستخدم مرتبط به.
     */
    public function store(Request $request)
    {
        $request->validate([
            'hospital_name' => 'required|string|max:255',
            'hospital_address' => 'required|string|max:255',
            'hospital_phone' => 'required|string|max:15|unique:hospitals,hospital_phone',
            'hospital_image' => 'nullable|string',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6'
        ]);
    
        // إنشاء مستخدم جديد
        $user = User::create([
            'name' => $request->hospital_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'hospital', // يتم تعيين نوع المستخدم تلقائيًا
            'is_active' => 1
        ]);
    
        // إنشاء المستشفى وربطه بالمستخدم
        $hospital = Hospital::create([
            'hospital_name' => $request->hospital_name,
            'hospital_address' => $request->hospital_address,
            'hospital_phone' => $request->hospital_phone,
            'hospital_image' => $request->hospital_image,
            'user_id' => $user->user_id
        ]);
    
        // 🔹 **تحديث جدول `users` لإضافة `hospital_id` للمستشفى**
        $user->hospital_id = $hospital->hospital_id;
        $user->save();
    
        return response()->json([
            'message' => 'Hospital added successfully',
            'hospital' => $hospital,
            'user' => $user
        ], 201);
    }
    
    /**
     * تحديث بيانات المستشفى.
     */
    public function update(Request $request, $id)
    {
        $hospital = Hospital::find($id);

        if (!$hospital) {
            return response()->json(['message' => 'Hospital not found'], 404);
        }

        $request->validate([
            'hospital_name' => 'required|string|max:255',
            'hospital_address' => 'required|string|max:255',
            'hospital_phone' => "required|string|max:15|unique:hospitals,hospital_phone,{$id},hospital_id",
            'hospital_image' => 'nullable|string'
        ]);

        $hospital->update($request->all());

        return response()->json(['message' => 'Hospital updated successfully', 'hospital' => $hospital], 200);
    }

    /**
     * حذف مستشفى وحذف المستخدم المرتبط به.
     */
    public function destroy($id)
    {
        $hospital = Hospital::find($id);

        if (!$hospital) {
            return response()->json(['message' => 'Hospital not found'], 404);
        }

        // حذف المستخدم المرتبط
        if ($hospital->user) {
            $hospital->user->delete();
        }

        $hospital->delete();

        return response()->json(['message' => 'Hospital deleted successfully'], 200);
    }
}
