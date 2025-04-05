<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    // تعطيل إدارة التواريخ التلقائية إذا لم تكن تستخدمها
    public $timestamps = true;  // تستخدم Laravel بشكل افتراضي for created_at و updated_at

    // تعريف الحقول القابلة للتحديث (Mass Assignment)
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'hospital_id',
        'patient_status',
        'notes'
    ];

    // الحقول التي لا تحتاج لتخزينها تلقائيًا
    protected $guarded = [];

    // الحقول التي هي تواريخ
    protected $dates = ['created_at', 'updated_at'];

    // العلاقات

    // علاقة مع نموذج المريض
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    // علاقة مع نموذج الطبيب
    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    // علاقة مع نموذج المستشفى
    public function hospital()
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    // يمكنك إضافة علاقة مع الأدوية إذا كان لديك جدول لتخزين الأدوية أيضًا
    public function medications()
    {
        return $this->belongsToMany(Medication::class, 'record_medications', 'medical_record_id', 'medication_id')
                   ; // لتخزين جرعة الدواء وتكرار استخدامه
    }
    // يمكنك إضافة علاقة مع الفحوصات إذا كان لديك جدول لتخزين الفحوصات أيضًا
   // علاقة بين MedicalRecord و Test
public function tests()
{
    return $this->belongsToMany(Test::class, 'medical_record_tests', 'medical_record_id', 'test_id')
                ->withPivot('result_value'); // إضافة result_value في جدول الربط
}

    // العلاقة مع جدول الربط medical_record_tests

    public function medicalRecordTests()
    {
        return $this->hasMany(MedicalRecordTest::class, 'medical_record_id', 'medical_record_id');
    }
    

    // العلاقة مع جدول الربط record_medications
  
public function recordMedications()
{
    return $this->hasMany(RecordMedication::class, 'medical_record_id', 'medical_record_id');
}

}

  
