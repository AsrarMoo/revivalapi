<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("CREATE DEFINER=`root`@`localhost` PROCEDURE `GetMedicalRecordDetails`(IN `p_patient_id` INT, IN `p_record_date` DATE)
BEGIN
    SELECT * 
    FROM view_medical_record_details 
    WHERE patient_id = p_patient_id AND record_date = p_record_date;
END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS GetMedicalRecordDetails");
    }
};
