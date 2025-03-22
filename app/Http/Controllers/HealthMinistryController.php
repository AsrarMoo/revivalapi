<?php

namespace App\Http\Controllers;

use App\Models\HealthMinistry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class HealthMinistryController extends Controller
{
    /**
     * إرجاع جميع وزارات الصحة.
     */
    public function index()
    {
        return response()->json(HealthMinistry::all(), 200);
    }

    /**
     * إرجاع وزارة صحة معينة.
     */
    public function show($id)
    {
        $ministry = HealthMinistry::find($id);

        if (!$ministry) {
            return response()->json(['message' => 'Health Ministry not found'], 404);
        }

        return response()->json($ministry, 200);
    }

    /**
     * إنشاء وزارة صحة جديدة مع مستخدم مرتبط بها.
     */
    public function store(Request $request)
{
    $request->validate([
        'health_ministry_name' => 'required|string|max:255', // اسم الوزارة مطلوب
        'phone' => 'required|string|max:15|unique:health_ministry,phone',
        'email' => 'required|string|email|max:255|unique:users,email',
        'password' => 'required|string|min:6',
    ]);

    try {
        DB::beginTransaction(); // بدء المعاملة

        // إنشاء وزارة الصحة بدون user_id في البداية
        $health_ministry = HealthMinistry::create([
            'health_ministry_name' => $request->health_ministry_name,
            'phone' => $request->phone,
        ]);

        if (!$health_ministry) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create health ministry'], 500);
        }

        // إنشاء المستخدم وربطه بمعرف الوزارة
        $user = User::create([
            'name' => $request->health_ministry_name, // ✅ إضافة الاسم هنا
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'healthMinistry',
            'is_active' => 1,
            'health_ministry_id' => $health_ministry->health_ministry_id, // ربط الوزارة بالمستخدم
        ]);

        if (!$user) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create user'], 500);
        }

        // تحديث وزارة الصحة بمعرف المستخدم بعد إنشائه
        $health_ministry->update([
            'user_id' => $user->user_id
        ]);

        DB::commit(); // تأكيد العملية

        return response()->json([
            'message' => 'Health Ministry added successfully',
            'ministry' => $health_ministry,
            'user' => $user
        ], 201);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
    }
}


    /**
     * تحديث بيانات وزارة الصحة.
     */
    public function update(Request $request, $id)
    {
        $ministry = HealthMinistry::find($id);

        if (!$ministry) {
            return response()->json(['message' => 'Health Ministry not found'], 404);
        }

        $request->validate([
            'health_ministry_name' => 'required|string|max:255', // تحديث الاسم مطلوب
            'phone' => "required|string|max:15|unique:health_ministry,phone,{$id},health_ministry_id",
        ]);

        $ministry->update($request->only(['health_ministry_name', 'phone'])); // تحديث الاسم والهاتف

        return response()->json(['message' => 'Health Ministry updated successfully', 'ministry' => $ministry], 200);
    }

    /**
     * حذف وزارة الصحة والمستخدم المرتبط بها.
     */
    public function destroy($id)
    {
        $ministry = HealthMinistry::find($id);

        if (!$ministry) {
            return response()->json(['message' => 'Health Ministry not found'], 404);
        }

        // حذف المستخدم المرتبط
        if ($ministry->user) {
            $ministry->user->delete();
        }

        $ministry->delete();

        return response()->json(['message' => 'Health Ministry deleted successfully'], 200);
    }
}
