<?php
namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TipLikeController extends Controller {

    // تسجيل إعجاب
    public function likeTip($tip_id) {
        $user_id = Auth::id();  // الحصول على الـ user_id من التوكن

        // التحقق إذا كان المستخدم قد أعجب بهذه النصيحة من قبل
        $existing_like = Like::where('tip_id', $tip_id)->where('user_id', $user_id)->first();
        if ($existing_like) {
            return response()->json(['message' => 'لقد أعجبت بهذه النصيحة مسبقًا!'], 400);  // رسالة مع كود 400 إذا كان قد أعجب بها مسبقًا
        }

        // إذا لم يعجب بها، نقوم بإضافة إعجاب جديد
        Like::insert([
            'tip_id' => $tip_id,
            'user_id' => $user_id,
            'created_at' => now()
        ]);

        return response()->json(['message' => 'تم تسجيل الإعجاب!'], 200);  // رسالة مع كود 200 لإعلام المستخدم بنجاح العملية
    }

    // إلغاء الإعجاب
    public function unlikeTip($tip_id) {
        $user_id = Auth::id();  // الحصول على الـ user_id من التوكن

        // التحقق من وجود الإعجاب قبل حذفه
        $existing_like = Like::where('tip_id', $tip_id)->where('user_id', $user_id)->first();
        if (!$existing_like) {
            return response()->json(['message' => 'لم تقم بالإعجاب بهذه النصيحة من قبل!'], 400);  // رسالة مع كود 400 إذا لم يكن هناك إعجاب
        }

        // حذف الإعجاب إذا كان موجودًا
        Like::where('tip_id', $tip_id)->where('user_id', $user_id)->delete();

        return response()->json(['message' => 'تم إلغاء الإعجاب!'], 200);  // رسالة مع كود 200 لإعلام المستخدم بنجاح العملية
    }

    // جلب النصائح مع عدد الإعجابات وحالة الإعجاب للمستخدم
    public function getTips() {
        $user_id = Auth::id();  // الحصول على الـ user_id من التوكن
        
        $tips = Tip::select(
            'medical_tips.tip_id', 
            'medical_tips.content', 
            'doctors.doctor_name',  // جلب اسم الطبيب
            'medical_tips.created_at'
        )
        ->leftJoin('tip_likes', 'medical_tips.tip_id', '=', 'tip_likes.tip_id')
        ->leftJoin('doctors', 'medical_tips.doctor_id', '=', 'doctors.doctor_id')
        ->selectRaw('COUNT(tip_likes.like_id) as like_count')
        ->groupBy(
            'medical_tips.tip_id', 
            'medical_tips.content', 
            'doctors.doctor_name', 
            'medical_tips.created_at'
        )
        ->orderBy('like_count', 'DESC')
        ->orderBy('medical_tips.created_at', 'DESC')
        ->get();

        // إضافة حالة الإعجاب للمستخدم
        foreach ($tips as $tip) {
            // التحقق إذا كان المستخدم قد أعجب بهذه النصيحة
            $tip->liked_by_user = Like::where('tip_id', $tip->tip_id)
                ->where('user_id', $user_id)
                ->exists();  // إذا كان موجودًا يكون true، إذا لا يكون false
        }
        
        return response()->json($tips);  // إرجاع النصائح مع حالة الإعجاب
    }
}
