<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model {
    use HasFactory;

    protected $table = 'tip_likes'; // اسم الجدول
    protected $primaryKey = 'like_id'; // المفتاح الأساسي
    protected $fillable = ['tip_id', 'user_id'];
    public $timestamps = false;

    // العلاقة مع النصائح
    public function tip() {
        return $this->belongsTo(Tip::class, 'tip_id');
    }

    // العلاقة مع المستخدمين
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
