<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;
    
    
    protected $table = 'specialties';
    protected $primaryKey = 'specialty_id';
    public $timestamps = true;

    protected $fillable = [
        'specialty_name',
        'specialty_description',
    ];
}