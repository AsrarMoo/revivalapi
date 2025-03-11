<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthMinistry extends Model
{
    use HasFactory;
    protected $table = 'health_ministry';
    protected $primaryKey = 'health_ministry_id'; // تحديد المفتاح الأساسي

    protected $fillable = [
        'name',
        'phone',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
