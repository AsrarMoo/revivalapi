<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;
   
        protected $primaryKey = 'patient_id'; // 🔥 تحديد العمود الأساسي
    
    
        protected $fillable = [
            'user_id',
            'patient_name',
            'patient_age' ,
            'patient_birthdate',
            'patient_blood_type',
            'patient_phone',
            'patient_address',
            'patient_status',
            'patient_height',
            'patient_weight',
            'patient_nationality',
            'patient_gender',
            'patient_image',
            'patient_notes',
        ];
        
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
    public function ambulanceRescues()
    {
        return $this->hasMany(AmbulanceRescue::class);
    }
}
