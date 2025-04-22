<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingDoctor extends Model
{
    protected $table = 'pending_doctors';

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'specialty_id',
        'qualification',
        'experience',
        'bio',
        'certificate_path',
        'image_path',
        'status',
    ];

    protected $hidden = ['password']; // عشان ما تظهر كلمة المرور في الواجهة
}
