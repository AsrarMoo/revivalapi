<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctors';

    // تحديد المفتاح الأساسي
    protected $primaryKey = 'doctor_id';

    // الحقول القابلة للتعبئة
    protected $fillable = [
        'doctor_name',
        'doctor_gender',
        'doctor_address',
        'doctor_image',
        'specialty_id',
        'user_id',
        'doctor_qualification',
        'doctor_phone',
        'doctor_email',
        'password', // إضافة كلمة السر كحقل قابل للتعبئة
    ];

    // علاقة مع جدول التخصصات (Specialties)
    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }

    // علاقة مع جدول المستخدمين (Users)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
