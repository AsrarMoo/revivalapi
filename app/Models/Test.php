<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasFactory;

    protected $primaryKey = 'test_id';

    protected $fillable = [
        'test_name',
        'test_description',
    ];

    public $timestamps = true;

    public function medicalRecordTests()
{
    return $this->hasMany(MedicalRecordTest::class, 'test_id');
}
}
