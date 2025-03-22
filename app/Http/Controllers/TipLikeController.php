<?php
namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TipLikeController extends Controller {
    // تسجيل إعجاب
    public function likeTip($tip_id) {
        $user_id = Auth::id();

        // التحقق من أن المستخدم لم يعجب بهذه النصيحة مسبقًا
        $existing_like = Like::where('tip_id', $tip_id)->where('user_id', $user_id)->first();
        if ($existing_like) {
            return response()->json(['message' => 'لقد أعجبت بهذه النصيحة مسبقًا!'], 400);
        }

        // إنشاء إعجاب جديد
        Like::insert([
            'tip_id' => $tip_id,
            'user_id' => $user_id,
            'created_at' => now() // ✅ إدخال `created_at` يدويًا
        ]);
        

        return response()->json(['message' => 'تم تسجيل الإعجاب!'], 200);
    }

    // إلغاء الإعجاب
    public function unlikeTip($tip_id) {
        $user_id = Auth::id();

        // حذف الإعجاب إن وجد
        Like::where('tip_id', $tip_id)->where('user_id', $user_id)->delete();

        return response()->json(['message' => 'تم إلغاء الإعجاب!'], 200);
    }

    // عرض النصائح مرتبة حسب عدد الإعجابات
    public function getTips() {
        $tips = Tip::select(
            'medical_tips.tip_id', 
            //'medical_tips.title', 
            'medical_tips.content', 
            'medical_tips.doctor_id', 
            'medical_tips.created_at'
        )
        ->leftJoin('tip_likes', 'medical_tips.tip_id', '=', 'tip_likes.tip_id')
        ->selectRaw('COUNT(tip_likes.like_id) as like_count')
        ->groupBy('medical_tips.tip_id',  'medical_tips.content', 'medical_tips.doctor_id', 'medical_tips.created_at')
        ->orderBy('like_count', 'DESC')
        ->orderBy('medical_tips.created_at', 'DESC')
        ->get();
    


        return response()->json($tips);
    }
}
