<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UserController extends Controller
{
    // جلب جميع المستخدمين
    public function index()
    {
        $users = User::with([
            'doctor:doctor_id,doctor_name',
            'hospital:hospital_id,hospital_name',
            'healthMinistry:health_ministry_id,health_ministry_name',
            'patient:patient_id,patient_name'
        ])->get();
    
        // تعديل البيانات لإرجاع الأسماء بدل المعرفات
        $users = $users->map(function ($user) {
            return [
                'user_id' => $user->user_id,
                //'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'is_active' => $user->is_active ? 1 : 0,  // تغيير هنا لاستخدام 1 أو 0 بدلاً من true أو false
                'associated_name' => match ($user->user_type) {
                    'doctor' => $user->doctor?->doctor_name,
                    'hospital' => $user->hospital?->hospital_name,
                    'healthMinistry' => $user->healthMinistry?->health_ministry_name,
                    'patient' => $user->patient?->patient_name,
                    default => null,
                },
                'created_at' => Carbon::parse($user->created_at)->format('Y-m-d h:i A'), // ⬅️ صيغة مفهومة
                'updated_at' => Carbon::parse($user->updated_at)->format('Y-m-d h:i A'),
            ];
        });
    
        return response()->json($users);
    }
    
    // جلب مستخدم واحد
    public function show($id)
    {
        $user = User::with([
            'doctor:doctor_id,doctor_name',
            'hospital:hospital_id,hospital_name',
            'healthMinistry:health_ministry_id,health_ministry_name',
            'patient:patient_id,patient_name'
        ])->find($id);
    
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        // تعديل البيانات لإرجاع الاسم المرتبط بالمستخدم
        $userData = [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'is_active' => $user->is_active ? 1 : 0,  // تغيير هنا لاستخدام 1 أو 0 بدلاً من true أو false
            'associated_name' => match ($user->user_type) {
                'doctor' => $user->doctor?->doctor_name,
                'hospital' => $user->hospital?->hospital_name,
                'healthMinistry' => $user->healthMinistry?->health_ministry_name,
                'patient' => $user->patient?->patient_name,
                default => null,
            },
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    
        return response()->json($userData);
    }
    
    // تحديث بيانات مستخدم
    public function update(Request $request, $id)
    {
        $user = User::with([
            'doctor:doctor_id,doctor_name',
            'hospital:hospital_id,hospital_name',
            'healthMinistry:health_ministry_id,health_ministry_name',
            'patient:patient_id,patient_name'
        ])->find($id);
    
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        $request->validate([
            'email' => "sometimes|string|email|max:255|unique:users,email,$id,user_id",
            'password' => 'sometimes|string|min:6',
            'user_type' => ['sometimes', Rule::in(['patient', 'doctor', 'hospital', 'admin'])],
            'is_active' => 'sometimes|in:0,1', // التحقق من أن القيمة إما 0 أو 1
        ]);
    
        // تأكد من أن قيمة is_active هي إما 1 أو 0
        $is_active = in_array($request->is_active, [0, 1]) ? $request->is_active : $user->is_active;
    
        $user->update([
            'email' => $request->email ?? $user->email,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
            'user_type' => $request->user_type ?? $user->user_type,
            'is_active' => $is_active,  // استخدام القيمة المعدلة
            'doctor_id' => $request->doctor_id ?? $user->doctor_id,
            'hospital_id' => $request->hospital_id ?? $user->hospital_id,
            'health_ministry_id' => $request->health_ministry_id ?? $user->health_ministry_id,
            'patient_id' => $request->patient_id ?? $user->patient_id,
        ]);
    
        // تحديث البيانات المرتبطة بعد التعديل
        $user->load([
            'doctor:doctor_id,doctor_name',
            'hospital:hospital_id,hospital_name',
            'healthMinistry:health_ministry_id,health_ministry_name',
            'patient:patient_id,patient_name'
        ]);
    
        $userData = [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'is_active' => $user->is_active ? 1 : 0,  // تغيير هنا لاستخدام 1 أو 0 بدلاً من true أو false
            'associated_name' => match ($user->user_type) {
                'doctor' => $user->doctor?->doctor_name,
                'hospital' => $user->hospital?->hospital_name,
                'healthMinistry' => $user->healthMinistry?->health_ministry_name,
                'patient' => $user->patient?->patient_name,
                default => null,
            },
            'created_at' => Carbon::parse($user->created_at)->format('Y-m-d h:i A'),
            'updated_at' => Carbon::parse($user->updated_at)->format('Y-m-d h:i A'),
        ];
    
        return response()->json($userData);
    }

    // جلب جميع أسماء المستخدمين فقط
    public function getNames()
    {
        $users = User::with([
            'doctor:doctor_id,doctor_name',
            'hospital:hospital_id,hospital_name',
            'healthMinistry:health_ministry_id,health_ministry_name',
            'patient:patient_id,patient_name'
        ])->get();

        $names = $users->map(function ($user) {
            return $user->doctor?->doctor_name ??
                   $user->hospital?->hospital_name ??
                   $user->healthMinistry?->health_ministry_name ??
                   $user->patient?->patient_name ??
                   null;
        })->filter(); // حذف القيم الفارغة

        return response()->json($names);
    }

    // إرجاع قائمة بأنواع المستخدمين يدويًا
    public function getUserTypes()
    {
        $types = ['patient', 'doctor', 'hospital', 'admin'];
        return response()->json($types);
    }

    // حذف مستخدم
    public function destroy($id)
    {
        $user = User::with([
            'doctor:doctor_id,doctor_name',
            'hospital:hospital_id,hospital_name',
            'healthMinistry:health_ministry_id,health_ministry_name',
            'patient:patient_id,patient_name'
        ])->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // حفظ بيانات المستخدم قبل الحذف لعرضها في الاستجابة
        $userData = [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'associated_name' => match ($user->user_type) {
                'doctor' => $user->doctor?->doctor_name,
                'hospital' => $user->hospital?->hospital_name,
                'healthMinistry' => $user->healthMinistry?->health_ministry_name,
                'patient' => $user->patient?->patient_name,
                default => null,
            }
        ];

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
            'deleted_user' => $userData
        ]);
    }
}
