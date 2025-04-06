<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctors'; // اسم الجدول
    protected $primaryKey = 'doctor_id'; // المفتاح الأساسي

    protected $fillable = [
        'doctor_name', 'specialty_id', 'doctor_qualification',
        'doctor_experience', 'doctor_phone', 'doctor_bio',
        'doctor_image', 'doctor_gender','user_id'
        
    ];

    // علاقة مع المستخدم
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
    public function specialty()
{
    return $this->belongsTo(Specialty::class, 'specialty_id');
}
// في نموذج Doctor

public function medicalRecords()
{
    return $this->hasMany(MedicalRecord::class, 'doctor_id');
}
public function hospitals()
{
    return $this->belongsToMany(Hospital::class, 'hospital_doctors', 'doctor_id', 'hospital_id');
}

}
