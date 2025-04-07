<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;

    protected $primaryKey = 'hospital_id';

    protected $fillable = [
        'hospital_name',
        'hospital_address',
        'hospital_phone',
        'hospital_image',
        'user_id',
        //'latitude',      
        //'longitude' 
        
    ];

   
    // App\Models\Hospital.php

// App\Models\Hospital.php

public function user()
{
    return $this->belongsTo(User::class);
}
public function ambulanceRescues()
{
    return $this->hasMany(AmbulanceRescue::class);
}

}
