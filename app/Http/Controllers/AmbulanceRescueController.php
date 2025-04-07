<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\MedicalRecord;
use App\Models\RecordMedication;
use App\Models\MedicalRecordTest;
use App\Models\AmbulanceRescue;
use App\Models\Hospital;
use App\Models\Doctor;
use App\Models\Medication;
use App\Models\Test;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AmbulanceRescueController extends Controller
{
    public function getPatientDataForHospital()
    {
        $hospital_id = Auth::user()->hospital_id;
        $rescuedPatients = AmbulanceRescue::where('hospital_id', $hospital_id)->get();

        if ($rescuedPatients->isEmpty()) {
            return response()->json(['message' => 'لا يوجد مرضى تم إسعافهم من قبل هذه المستشفى.'], 404);
        }

        $patientData = [];

        foreach ($rescuedPatients as $rescue) {
            $patient = Patient::find($rescue->patient_id);

            if ($patient) {
                $medicalRecords = MedicalRecord::where('patient_id', $patient->patient_id)->get();

                // إذا ما عنده ولا سجل طبي
                if ($medicalRecords->isEmpty()) {
                    $patientData[] = [
                        'patient' => [
                            'patient_name' => $patient->patient_name,
                            'patient_age' => $patient->patient_age,
                            'patient_blood_type' => $patient->patient_blood_type,
                            'patient_gender' => $patient->patient_gender,
                        ],
                        'message' => 'لا يوجد سجل طبي خاص بهذا المريض.'
                    ];
                    continue; // ننتقل للمريض التالي
                }

                $formattedRecords = [];

                foreach ($medicalRecords as $record) {
                    $doctor = Doctor::find($record->doctor_id);
                    $hospital = Hospital::find($record->hospital_id);

                    // الأدوية الخاصة بهذا السجل
                    $recordMedications = RecordMedication::where('medical_record_id', $record->medical_record_id)->get();
                    $medications = [];
                    foreach ($recordMedications as $medication) {
                        $name = Medication::find($medication->medication_id)?->medication_name;
                        if ($name && !in_array($name, $medications)) {
                            $medications[] = $name;
                        }
                    }

                    // الفحوصات الخاصة بهذا السجل
                    $recordTests = MedicalRecordTest::where('medical_record_id', $record->medical_record_id)->get();
                    $tests = [];
                    foreach ($recordTests as $test) {
                        $testModel = Test::find($test->test_id);
                        if ($testModel) {
                            $tests[] = [
                                'test_name' => $testModel->test_name,
                                'result' => $test->result_value
                            ];
                        }
                    }

                    $formattedRecords[] = [
                        'medical_record_id' => $record->medical_record_id,
                        'patient_status' => $record->patient_status,
                        'notes' => $record->notes,
                        'created_at' => Carbon::parse($record->created_at)->format('Y-m-d h:i A'),
                        'updated_at' => Carbon::parse($record->updated_at)->format('Y-m-d h:i A'),
                        'doctor_name' => $doctor?->doctor_name,
                        'hospital_name' => $hospital?->hospital_name,
                        'medications' => $medications,
                        'tests' => $tests,
                    ];
                }

                $patientData[] = [
                    'patient' => [
                        'patient_name' => $patient->patient_name,
                        'patient_age' => $patient->patient_age,
                        'patient_blood_type' => $patient->patient_blood_type,
                        'patient_gender' => $patient->patient_gender,
                    ],
                    'medical_records' => $formattedRecords
                ];
            }
        }

        return response()->json(['rescued_patients' => $patientData]);
    }
}
