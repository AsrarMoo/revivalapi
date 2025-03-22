<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalDoctorRequest extends Model
{
    use HasFactory;
    public $timestamps = false;
    
    protected $primaryKey = 'request_id'; // استبدل request_id بالاسم الفعلي لمفتاحك الأساسي
    protected $table = 'hospital_doctor_requests';

    protected $fillable = ['hospital_id', 'doctor_id', 'status'];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }
 



}

