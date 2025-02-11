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
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
// **المصادقة (Authentication)**
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('jwt.auth');
// **مسارات المستخدمين (Users)**
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);       // جميع المستخدمين
    Route::get('/{id}', [UserController::class, 'show']);    // مستخدم معين
    Route::post('/', [UserController::class, 'store']);      // إضافة مستخدم
    Route::put('/{id}', [UserController::class, 'update']);  // تعديل بيانات المستخدم
    Route::delete('/{id}', [UserController::class, 'destroy']); // حذف مستخدم
});
// **مسارات المرضى (Patients)**
Route::prefix('patients')->group(function () {
    Route::get('/', [PatientController::class, 'index']);
    Route::get('/{id}', [PatientController::class, 'show']);
    Route::post('/', [PatientController::class, 'store']);
    Route::put('/{id}', [PatientController::class, 'update']);
    Route::delete('/{id}', [PatientController::class, 'destroy']);
});
// **مسارات الأطباء (Doctors)**
Route::prefix('doctors')->group(function () {
    Route::get('/', [DoctorController::class, 'index']);
    Route::get('/{id}', [DoctorController::class, 'show']);
    Route::post('/', [DoctorController::class, 'store']);
    Route::put('/{id}', [DoctorController::class, 'update']);
    Route::delete('/{id}', [DoctorController::class, 'destroy']);
});
// **مسارات المستشفيات (Hospitals)**
Route::prefix('hospitals')->group(function () {
    Route::get('/', [HospitalController::class, 'index']);
    Route::get('/{id}', [HospitalController::class, 'show']);
    Route::post('/', [HospitalController::class, 'store']);
    Route::put('/{id}', [HospitalController::class, 'update']);
    Route::delete('/hospitals/{id}', [HospitalController::class, 'destroy']);
});
// **مسارات المسؤولين (Admins)**
Route::prefix('admins')->group(function () {
    Route::get('/', [AdminController::class, 'index']);
    Route::get('/{id}', [AdminController::class, 'show']);
    Route::post('/', [AdminController::class, 'store']);
    Route::put('/{id}', [AdminController::class, 'update']);
    Route::delete('/{id}', [AdminController::class, 'destroy']);
});
// **استرجاع بيانات المستخدم المسجل حاليًا**
Route::middleware('jwt.auth')->get('/user', function (Request $request) {
    return $request->user();
});
