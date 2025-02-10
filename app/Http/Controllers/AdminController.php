<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\User;

class AdminController extends Controller
{
    // إضافة مدير جديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'admin_name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ]);

        // إنشاء مستخدم في جدول المستخدمين
        $user = User::create([
            'name' => $validated['admin_name'],
            'password' => bcrypt($validated['password']), // تشفير كلمة المرور
            'user_type' => 'Admin', // تحديد نوع المستخدم كـ Admin
            'is_active' => 1, // جعل الحساب مفعلًا افتراضياً
        ]);

        // إنشاء مدير في جدول المدراء وربط الـ user_id
        $admin = Admin::create([
            'admin_name' => $validated['admin_name'],
            'user_id' => $user->user_id,
        ]);

        return response()->json($admin, 201);
    }

    // عرض جميع المدراء
    public function index()
    {
        // استرجاع جميع المدراء
        $admins = Admin::all();

        // يمكنك أيضاً استرجاع المستخدمين المرتبطين بكل مدير
        foreach ($admins as $admin) {
            $admin->user = User::find($admin->user_id); // ربط المستخدم بالمدير
        }

        return response()->json($admins, 200);
    }

    // تعديل مدير موجود
    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json(['error' => 'Admin not found'], 404);
        }

        $validated = $request->validate([
            'admin_name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:6',
        ]);

        // تحديث بيانات المدير
        if (isset($validated['admin_name'])) {
            $admin->admin_name = $validated['admin_name'];
        }
        $admin->save();

        // تحديث بيانات المستخدم المرتبطة
        $user = User::find($admin->user_id);
        if ($user) {
            if (isset($validated['admin_name'])) {
                $user->name = $validated['admin_name'];
            }
            if (isset($validated['password'])) {
                $user->password = bcrypt($validated['password']); // تشفير كلمة المرور
            }
            $user->save();
        }

        return response()->json($admin, 200);
    }

    // حذف مدير
    public function destroy($id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json(['error' => 'Admin not found'], 404);
        }

        // حذف المستخدم المرتبط بالمدير
        $user = User::find($admin->user_id);
        if ($user) {
            $user->delete();
        }

        // حذف المدير
        $admin->delete();

        return response()->json(['message' => 'Admin deleted successfully'], 200);
    }
}
