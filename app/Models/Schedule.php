<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;
    protected $table='doctor_schedules';
    protected $primaryKey = 'schedule_id'; // المفتاح الأساسي
    protected $fillable = [
        'doctor_id',
        'hospital_id',
        'day_of_week',
        'start_time',
        'end_time',
        'status',
        'proposed_start_time',
        'proposed_end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}
