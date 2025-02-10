<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    // تحديد المفتاح الأساسي إذا كان غير الافتراضي
    protected $primaryKey = 'patient_id';  // هنا يتم تحديد المفتاح الأساسي

    // تعيين القيمة لتكون متزايدة تلقائيًا (إذا كان النوع هو int)
    public $incrementing = true; // إذا كنت تستخدم auto-increment للـ primary key

    // إضافة هذه لتسمح بالـ Mass Assignment
    protected $fillable = [
        'patient_name',
        'patient_age',
        'patient_gender',
        'patient_BD',
        'patient_status',
        'patient_height',
        'patient_weight',
        'patient_phone',
        'patient_email',
        'patient_nationality',
        'patient_bloodType',
        'patient_address',
        'patient_image',
        'user_id',
    ];
}
