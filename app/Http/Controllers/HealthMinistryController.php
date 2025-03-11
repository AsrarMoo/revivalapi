<?php

namespace App\Http\Controllers;

use App\Models\HealthMinistry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:15|unique:health_ministry,phone',
        'email' => 'required|string|email|max:255|unique:users,email',
        'password' => 'required|string|min:6',
    ]);

    try {
        \DB::beginTransaction(); // بدء معاملة لحفظ البيانات بأمان

        // إنشاء المستخدم أولًا
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'admin',
            'is_active' => 1,
        ]);

        if (!$user) {
            \DB::rollBack(); // إلغاء العملية إذا فشل إنشاء المستخدم
            return response()->json(['message' => 'Failed to create user'], 500);
        }

        // إنشاء وزارة الصحة وربطها بالمستخدم
        $health_ministry = HealthMinistry::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'user_id' => $user->user_id, // ربط وزارة الصحة بالمستخدم
        ]);

        if (!$health_ministry) {
            \DB::rollBack(); // إلغاء العملية إذا فشل إنشاء الوزارة
            return response()->json(['message' => 'Failed to create health ministry'], 500);
        }

        // تحديث المستخدم وإضافة health_ministry_id
        $user->health_ministry_id = $health_ministry->health_ministry_id;
        $user->save();

        \DB::commit(); // تأكيد حفظ البيانات

        return response()->json([
            'message' => 'Health Ministry added successfully',
            'ministry' => $health_ministry,
            'user' => $user
        ], 201);
    } catch (\Exception $e) {
        \DB::rollBack(); // التراجع في حالة حدوث خطأ
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
            'name' => 'required|string|max:255',
            'phone' => "required|string|max:15|unique:health_ministry,phone,{$id},health_ministry_id",
        ]);

        $ministry->update($request->all());

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
