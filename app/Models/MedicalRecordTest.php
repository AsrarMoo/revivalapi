<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecordTest extends Model
{
    use HasFactory;

    // تحديد اسم الجدول في قاعدة البيانات
    protected $table = 'medical_record_tests';  // يمكنك تغيير هذا إذا كان اسم الجدول مختلفًا
    public  $timestamps = true;

    // الحقول التي يمكن تعبئتها (mass assignable)
    protected $fillable = [
        'medical_record_id',
        'test_id',
        'result_value',
        'result_date',
    ];

    // تعريف العلاقة مع السجل الطبي (MedicalRecord)
    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    // تعريف العلاقة مع الفحص (Test)
    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id');
    }
}
