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
                'message' => 'لم يتم العثور على حساب طبيب مرتبط بهذا المستخدم.',
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
                'doctor_name' => $doctor->doctor_name, // ✅ إرجاع اسم الطبيب بدلًا من المعرف
            ]
        ], 201);
    }

    // ✅ عرض النصائح (كل النصائح أو نصائح الطبيب فقط)
    public function index(Request $request)
    {
        $doctorId = auth()->user()->doctor_id;

        if ($request->has('my_tips') && $request->my_tips == true) {
            // 🔹 جلب نصائح الطبيب فقط مع اسم الطبيب
            $tips = Tip::where('doctor_id', $doctorId)
                ->with('doctor:doctor_id,doctor_name') // 🔹 جلب الاسم فقط
                ->get();
        } else {
            // 🔹 جلب كل النصائح مع اسم الطبيب
            $tips = Tip::with('doctor:doctor_id,doctor_name')->get();
        }

        return response()->json([
            'tips' => $tips->map(function ($tip) {
                return [
                    'tip_id' => $tip->tip_id,
                    'content' => $tip->content,
                    'doctor_name' => $tip->doctor->doctor_name ?? 'غير معروف', // ✅ اسم الطبيب
                ];
            })
        ], 200);
    }

    // ✅ عرض نصيحة معينة
    public function show($id)
    {
        $tip = Tip::with('doctor:doctor_id,doctor_name')->find($id);

        if (!$tip) {
            return response()->json(['message' => 'النصيحة غير موجودة'], 404);
        }

        return response()->json([
            'tip' => [
                'id' => $tip->id,
                'content' => $tip->content,
                'doctor_name' => $tip->doctor->doctor_name ?? 'غير معروف', // ✅ اسم الطبيب
            ]
        ], 200);
    }

    // ✅ تعديل نصيحة (الطبيب يستطيع تعديل نصائحه فقط)
    public function update(Request $request, $id)
    {
        $tip = Tip::find($id);

        if (!$tip) {
            return response()->json(['message' => 'النصيحة غير موجودة'], 404);
        }

        if ($tip->doctor_id !== auth()->user()->doctor_id) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذه النصيحة'], 403);
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $tip->update(['content' => $request->content]);

        return response()->json([
            'message' => 'تم تعديل النصيحة بنجاح',
            'tip' => [
                'id' => $tip->id,
                'content' => $tip->content,
                'doctor_name' => $tip->doctor->doctor_name ?? 'غير معروف', // ✅ اسم الطبيب
            ]
        ], 200);
    }

    // ✅ حذف نصيحة (الطبيب يستطيع حذف نصائحه فقط)
    public function destroy($id)
    {
        $tip = Tip::find($id);

        if (!$tip) {
            return response()->json(['message' => 'النصيحة غير موجودة'], 404);
        }

        if ($tip->doctor_id !== auth()->user()->doctor_id) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذه النصيحة'], 403);
        }

        $tip->delete();

        return response()->json(['message' => 'تم حذف النصيحة بنجاح'], 200);
    }
}
