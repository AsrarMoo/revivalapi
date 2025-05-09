<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;
    protected $table = 'hospitals';
    protected $primaryKey = 'hospital_id';

    protected $fillable = [
        'hospital_name',
        'hospital_address',
        'hospital_phone',
        'hospital_image',
        'user_id',
        'latitude',
        'longitude',
        
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
