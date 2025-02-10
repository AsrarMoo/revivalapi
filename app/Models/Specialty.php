<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;

    protected $table = 'specialties';

    // الحقول القابلة للتعبئة
    protected $fillable = [
        'specialty_name',
    ];

    // علاقة مع جدول الأطباء (Doctors)
    public function doctors()
    {
        return $this->hasMany(Doctor::class, 'specialty_id');
    }
}
