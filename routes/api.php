<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use App\Http\Controllers\PatientController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;


Route::get('/patients', [PatientController::class, 'index']); // استعراض جميع المرضى
Route::get('/patients/{id}', [PatientController::class, 'show']); // استعراض مريض محدد
Route::post('/patients', [PatientController::class, 'store']); // إضافة مريض جديد
Route::put('/patients/{id}', [PatientController::class, 'update']); // تعديل مريض
Route::delete('/patients/{id}', [PatientController::class, 'destroy']); // حذف مريض

Route::get('/doctors', [DoctorController::class, 'index']); // استعراض جميع الأطباء
Route::get('/doctors/{id}', [DoctorController::class, 'show']); // استعراض طبيب محدد
Route::post('/doctors', [DoctorController::class, 'store']); // إضافة طبيب جديد
Route::put('/doctors/{id}', [DoctorController::class, 'update']); // تعديل طبيب
Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']); // حذف طبيب

Route::get('/hospitals', [HospitalController::class, 'index']);

// استعراض مستشفى محدد
Route::get('/hospitals/{hospital_id}', [HospitalController::class, 'show']);

// إضافة مستشفى جديد
Route::post('/hospitals', [HospitalController::class, 'store']);

// تعديل مستشفى
Route::put('/hospitals/{hospital_id}', [HospitalController::class, 'update']);

// حذف مستشفى
Route::delete('/hospitals/{hospital_id}', [HospitalController::class, 'destroy']);


Route::get('/admins', [AdminController::class, 'index']); // استعراض جميع المدراء
Route::get('/admins/{id}', [AdminController::class, 'show']); // استعراض مدير محدد
Route::post('/admins', [AdminController::class, 'store']); // إضافة مدير جديد
Route::put('/admins/{id}', [AdminController::class, 'update']); // تعديل مدير
Route::delete('/admins/{id}', [AdminController::class, 'destroy']); // حذف مدير







Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
