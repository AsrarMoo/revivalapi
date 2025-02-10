<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    protected $table = 'admins';

    // تحديد المفتاح الأساسي
    protected $primaryKey = 'admin_id';

    // الحقول القابلة للتعبئة
    protected $fillable = [
        'user_id',
        'admin_name',
    ];

    // علاقة مع جدول المستخدمين (Users)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
