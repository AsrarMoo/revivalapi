<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MedicalRecordController;

Route::get('/', function () {
    return response()->json([
        'message' => 'API is running successfully!',
        'status' => 200
    ]);
});


