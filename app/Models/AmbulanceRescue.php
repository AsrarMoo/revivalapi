<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmbulanceRescue extends Model
{
    use HasFactory;

    // تحديد اسم الجدول في قاعدة البيانات
    protected $table = 'ambulance_rescue';

    // تحديد الأعمدة القابلة للتحديث (Mass Assignment)
    protected $fillable = [
        'patient_id', 
        'hospital_id', 
        'rescued_by_name', 
        'latitude', 
        'longitude'
    ];

    // تعريف العلاقة مع مريض
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    // تعريف العلاقة مع مستشفى
    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}
