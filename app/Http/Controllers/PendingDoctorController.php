<?php

namespace App\Http\Controllers;

use App\Models\PendingDoctor;
use Illuminate\Http\Request;

class PendingDoctorController extends Controller
{
    // دالة لاستعراض جميع الأطباء
    public function index()
    {
        $doctors = PendingDoctor::all();
        return response()->json([
            'status' => true,
            'data' => $doctors
        ], 200);
    }
}
