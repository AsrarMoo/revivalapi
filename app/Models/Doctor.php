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
        'doctor_image', 'doctor_gender','user_id','doctor_certificate',
        
    ];

    // علاقة مع المستخدم
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
  // في موديل Doctor
  // العلاقة بين الطبيب والتخصص
  public function specialty()
  {
      return $this->belongsTo(Specialty::class, 'specialty_id', 'specialty_id');
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
public function doctor_rataing()
{
    return $this->hasMany(DoctorRating::class, 'doctor_id', 'doctor_id');
}

public function appointments()
{
    return $this->hasMany(Appointment::class, 'doctor_id');
}
}
