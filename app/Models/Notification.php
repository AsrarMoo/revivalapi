<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $primaryKey = 'notification_id'; // ✅ تأكد أن هذا مطابق لاسم المفتاح الأساسي في قاعدة البيانات

    protected $table = 'notifications';

    protected $fillable = ['user_id','created_by','request_id', 'title', 'message', 'type', 'is_read', 'created_at'];

    public $timestamps = false; // 🚀 يمنع Laravel من إضافة `updated_at`
   
   
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id')->select('user_id', 'name');
    }
    

}
