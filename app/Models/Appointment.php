<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Schedule;


class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments'; // تأكد أن اسم الجدول مطابق لقاعدة البيانات

    protected $primaryKey = 'appointment_id'; // تأكد أنه يطابق المفتاح الأساسي في الجدول

    protected $fillable = [
        'patient_id',
        'hospital_id',
        'doctor_id',
        'schedule_id',
        'status'
    ];



    // العلاقة مع المريض
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    // العلاقة مع الطبيب
    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }

    // العلاقة مع المستشفى
    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id', 'hospital_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }
   
}