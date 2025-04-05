<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingDoctor extends Model
{
    use HasFactory;

    protected $table = 'pending_doctors';

    protected $fillable = [
        'name','password', 'email', 'phone', 'gender', 'specialty_id',
        'qualification', 'experience', 'bio', 'license_path',
        'certificate_path', 'image_path', 'status'
    ];
}
