<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;

    // تعيين اسم الجدول إذا كان مختلفًا عن اسم الموديل
    protected $table = 'hospitals';

    // تحديد المفتاح الرئيسي
    protected $primaryKey = 'hospital_id'; // المفتاح الرئيسي هو 'hospital_id'

    // تحديد الأعمدة القابلة للتعديل (fillable)
    protected $fillable = [
       
            'hospital_name',
            'hospital_email',
            'hospital_address',
            'hospital_phone',
            'hospital_image', // الحقل الجديد
            'user_id',
        ];
        

    // علاقة مع نموذج المستخدم (User)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');  // المستخدم المرتبط بالمستشفى
    }

    // يمكن أيضًا إضافة أي تعديلات خاصة مثل الحقول المخفية أو التحويلات إذا لزم الأمر
}
