<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tip;
use App\Models\Doctor;

class TipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ✅ إضافة نصيحة جديدة (يجب أن يكون المستخدم طبيبًا)
    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        // 🔹 الحصول على بيانات المستخدم والطبيب
        $user = auth()->user();
        $doctor = $user->doctor; // العلاقة مع جدول الأطباء

        if (!$doctor) {
            return response()->json([
                'message' => 'يجب أن يكون لديك حساب طبيب لإضافة نصيحة.',
            ], 403);
        }

        // 🔹 إنشاء النصيحة
        $tip = Tip::create([
            'doctor_id' => $doctor->doctor_id,
            'content' => $request->content,
        ]);

        return response()->json([
            'message' => 'تمت إضافة النصيحة بنجاح',
            'tip' => [
                'tip_id' => $tip->tip_id,
                'content' => $tip->content,
                'doctor_name' => $doctor->doctor_name, // ✅ إرجاع اسم الطبيب
            ]
        ], 201);
    }

    // ✅ عرض جميع النصائح أو نصائح الطبيب فقط
    public function index(Request $request)
    {
        $user = auth()->user();
        $doctorId = $user->doctor->doctor_id ?? null;

        $tips = Tip::with('doctor:doctor_id,doctor_name')
            ->when($request->has('my_tips') && $request->my_tips == true, function ($query) use ($doctorId) {
                return $query->where('doctor_id', $doctorId);
            })
            ->get();

        return response()->json([
            'tips' => $tips->map(function ($tip) {
                return [
                    'tip_id' => $tip->tip_id,
                    'content' => $tip->content,
                    'doctor_name' => $tip->doctor->doctor_name ?? 'غير معروف',
                ];
            })
        ], 200);
    }

    // ✅ عرض نصيحة معينة
    public function show($id)
    {
        $tip = Tip::with('doctor:doctor_id,doctor_name')->findOrFail($id);

        return response()->json([
            'tip' => [
                'tip_id' => $tip->tip_id,
                'content' => $tip->content,
                'doctor_name' => $tip->doctor->doctor_name ?? 'غير معروف',
            ]
        ], 200);
    }

    // ✅ تعديل نصيحة (يجب أن يكون المستخدم صاحب النصيحة)
    public function update(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $tip = Tip::findOrFail($id);
        $user = auth()->user();

        if ($tip->doctor_id !== ($user->doctor->doctor_id ?? null)) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذه النصيحة'], 403);
        }

        $tip->update(['content' => $request->content]);

        return response()->json([
            'message' => 'تم تعديل النصيحة بنجاح',
            'tip' => [
                'tip_id' => $tip->tip_id,
                'content' => $tip->content,
                'doctor_name' => $tip->doctor->doctor_name ?? 'غير معروف',
            ]
        ], 200);
    }

    // ✅ حذف نصيحة (يجب أن يكون المستخدم صاحب النصيحة)
    public function destroy($id)
    {
        $tip = Tip::findOrFail($id);
        $user = auth()->user();

        if ($tip->doctor_id !== ($user->doctor->doctor_id ?? null)) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذه النصيحة'], 403);
        }

        $tip->delete();

        return response()->json(['message' => 'تم حذف النصيحة بنجاح'], 200);
    }
}
