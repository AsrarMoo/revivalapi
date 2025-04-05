<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens'; // اسم الجدول في قاعدة البيانات

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked',
        'ip_address',
        'user_agent'
    ];

    // العلاقة مع موديل User
 // في موديل RefreshToken
public function user()
{
    return $this->belongsTo(User::class, 'user_id'); // التأكد من أن المفتاح الصحيح
}
}