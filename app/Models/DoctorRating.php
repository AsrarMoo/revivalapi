<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorRating extends Model
{
    use HasFactory;

    protected $primaryKey = 'rating_id';
    public $timestamps = false; 
    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'professionalism',
        'communication',
        'listening',
        'knowledge_experience',
        'punctuality',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }
}