<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Patient;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class PatientController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register']]);
    }

    // ✅ تسجيل مريض جديد
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'patient_name' => 'required|string|max:255',
            'patient_age' => 'required|integer',
            'patient_birthdate' => 'required|date',
            'patient_blood_type' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'patient_phone' => 'required|string|max:15|unique:patients,patient_phone',
            'patient_gender' => 'required|in:male,female',
            'patient_address' => 'nullable|string|max:255',
            'patient_status' => 'required|in:single,married',
            'patient_height' => 'nullable|numeric',
            'patient_weight' => 'nullable|numeric',
            'patient_nationality' => 'nullable|string|max:100',
            'patient_image' => 'nullable|string|max:255',
            'patient_notes' => 'nullable|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6'
        ]);

        return DB::transaction(function () use ($validatedData) {
            $user = User::create([
                'name' => $validatedData['patient_name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'user_type' => 'patient',
            ]);

            $patient = Patient::create(array_merge($validatedData, ['user_id' => $user->user_id]));
            $user->update(['patient_id' => $patient->patient_id]);

            return response()->json(['message' => 'تم تسجيل المريض بنجاح', 'patient' => $patient, 'user' => $user], 201);
        });
    }

    // ✅ استعلام عن جميع المرضى
    public function index()
    {
        return response()->json(['patients' => Patient::all()], 200);
    }

    // ✅ استعلام عن مريض معين
    public function show($id)
    {
        $patient = Patient::find($id);
        return $patient ? response()->json(['patient' => $patient], 200)
                         : response()->json(['message' => 'المريض غير موجود'], 404);
    }

    // ✅ تحديث بيانات المريض
    public function update(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $request->validate([
            'patient_name' => 'sometimes|string|max:255',
            'patient_age' => 'sometimes|integer',
            'patient_birthdate' => 'sometimes|date',
            'patient_blood_type' => 'sometimes|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'patient_phone' => 'sometimes|string|max:15|unique:patients,patient_phone,' . $id . ',patient_id',
            'patient_gender' => 'sometimes|in:male,female',
            'patient_address' => 'sometimes|string|max:255',
            'patient_status' => 'sometimes|in:single,married',
            'patient_height' => 'sometimes|numeric',
            'patient_weight' => 'sometimes|numeric',
            'patient_nationality' => 'sometimes|string|max:100',
            'patient_image' => 'sometimes|string|max:255',
            'patient_notes' => 'sometimes|string',
        ]);

        $patient->update($request->all());
        return response()->json(['message' => 'تم تحديث بيانات المريض بنجاح', 'patient' => $patient], 200);
    }

    // ✅ حذف مريض
    public function destroy($id)
    {
        $patient = Patient::find($id);
        if (!$patient) {
            return response()->json(['message' => 'المريض غير موجود'], 404);
        }

        DB::transaction(function () use ($patient) {
            User::where('user_id', $patient->user_id)->delete();
            $patient->delete();
        });

        return response()->json(['message' => 'تم حذف المريض بنجاح'], 200);
    }
}
