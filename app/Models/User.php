<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users'; // اسم الجدول
    protected $primaryKey = 'user_id'; // 🔥 تحديد المفتاح الأساسي
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'is_active',
        'doctor_id',
        'hospital_id',
        'health_ministry_id',
        'patient_id'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ✅ العلاقة مع المرضى
    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id', 'user_id');
    }

    // ✅ العلاقة مع الأطباء
    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'doctor_id', 'doctor_id');
    }

    // ✅ العلاقة مع المستشفيات
    public function hospital()
    {
        return $this->hasOne(Hospital::class, 'hospital_id', 'hospital_id');
    }

    // ✅ العلاقة مع وزارة الصحة
    public function healthMinistry()
    {
        return $this->hasOne(HealthMinistry::class, 'health_ministry_id', 'health_ministry_id');
    }

    // ✅ دوال JWT
    public function getJWTIdentifier()
    {
        return $this->getKey(); // 🔥 استخدم getKey() لأنه أكثر أمانًا
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
