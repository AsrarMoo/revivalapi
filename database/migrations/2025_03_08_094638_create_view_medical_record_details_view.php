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
        DB::statement("CREATE VIEW `view_medical_record_details` AS select `mr`.`medical_record_id` AS `medical_record_id`,`mr`.`patient_id` AS `patient_id`,`p`.`patient_name` AS `patient_name`,`mr`.`doctor_id` AS `doctor_id`,`d`.`doctor_name` AS `doctor_name`,`mr`.`hospital_id` AS `hospital_id`,`h`.`hospital_name` AS `hospital_name`,`mr`.`diagnosis` AS `diagnosis`,`mr`.`patient_status` AS `patient_status`,`mr`.`notes` AS `notes`,`mr`.`created_at` AS `record_date`,`m`.`medication_id` AS `medication_id`,`m`.`medication_name` AS `medication_name`,`mrm`.`dosage` AS `dosage`,`mrm`.`duration` AS `duration`,`mt`.`test_id` AS `test_id`,`mt`.`test_name` AS `test_name`,`mrt`.`test_result` AS `test_result` from (((((((`revival`.`medical_records` `mr` join `revival`.`patients` `p` on(`mr`.`patient_id` = `p`.`patient_id`)) join `revival`.`doctors` `d` on(`mr`.`doctor_id` = `d`.`doctor_id`)) join `revival`.`hospitals` `h` on(`mr`.`hospital_id` = `h`.`hospital_id`)) left join `revival`.`medical_record_medications` `mrm` on(`mr`.`medical_record_id` = `mrm`.`medical_record_id`)) left join `revival`.`medications` `m` on(`mrm`.`medication_id` = `m`.`medication_id`)) left join `revival`.`medical_record_tests` `mrt` on(`mr`.`medical_record_id` = `mrt`.`medical_record_id`)) left join `revival`.`medical_tests` `mt` on(`mrt`.`test_id` = `mt`.`test_id`))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS `view_medical_record_details`");
    }
};
