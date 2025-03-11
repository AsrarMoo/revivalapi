<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // جلب جميع المستخدمين
    public function index()
    {
        return response()->json(User::all());
    }

    // إنشاء مستخدم جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'user_type' => ['required', Rule::in(['patient', 'doctor', 'hospital', 'admin'])],
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'is_active' => $request->is_active ?? 1,
            'doctor_id' => $request->doctor_id,
            'hospital_id' => $request->hospital_id,
            'health_ministry_id' => $request->health_ministry_id,
            'patient_id' => $request->patient_id,
        ]);

        return response()->json($user, 201);
    }

    // جلب مستخدم واحد
    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    // تحديث بيانات مستخدم
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|string|email|max:255|unique:users,email,$id,user_id",
            'password' => 'sometimes|string|min:6',
            'user_type' => ['sometimes', Rule::in(['patient', 'doctor', 'hospital', 'admin'])],
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
            'user_type' => $request->user_type ?? $user->user_type,
            'is_active' => $request->is_active ?? $user->is_active,
            'doctor_id' => $request->doctor_id ?? $user->doctor_id,
            'hospital_id' => $request->hospital_id ?? $user->hospital_id,
            'health_ministry_id' => $request->health_ministry_id ?? $user->health_ministry_id,
            'patient_id' => $request->patient_id ?? $user->patient_id,
        ]);

        return response()->json($user);
    }

    // حذف مستخدم
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
