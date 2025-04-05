<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordMedication extends Model
{
    // العلاقة مع نموذج السجل الطبي
    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    // العلاقة مع نموذج الدواء
    public function medication()
    {
        return $this->belongsTo(Medication::class, 'medication_id');
    }
}
