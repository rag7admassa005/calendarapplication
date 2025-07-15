<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ManageAppointmentController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SuperadminController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// -------------------- Auth Routes --------------------

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verifyCode/{user_id}', [AuthController::class, 'verifyCode']);
Route::post('/resendCode/{user_id}', [AuthController::class, 'resendVerificationCode']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgotpassword', [AuthController::class, 'forgetPassword']);
Route::post('/resetcode', [AuthController::class, 'confirmResetCode']);
Route::middleware('auth:api')->get('/viewProfile', [AuthController::class, 'viewProfile']);
Route::middleware('auth:api')->post('/updateProfile', [AuthController::class, 'updateProfile']);
Route::middleware('auth:api')->delete('/logout', [AuthController::class, 'logout']);

// -------------------- Superadmin Routes --------------------

Route::post('/superadmin/login', [SuperadminController::class, 'login']);
Route::middleware('superadmin')->group(function () {
    Route::post('/managers', [SuperadminController::class, 'addManager']);
    Route::post('/managers/{id}', [SuperadminController::class, 'deleteManager']);
    Route::get('/managers', [SuperadminController::class, 'showAllManagers']);
    Route::get('/managers/{id}', [SuperadminController::class, 'showManager']);
});

// -------------------- Manager Routes --------------------

Route::post('
', [ManagerController::class, 'setManagerPassword']);
Route::post('/manager/login', [ManagerController::class, 'managerLogin']);
Route::post('/manager/verify-code', [ManagerController::class, 'resendVerificationCode']);

// -------------------- Job Routes --------------------

// عامة: يمكن للمستخدم رؤية وظائف مدير معين
Route::get('/managers/{manager_id}/jobs', [JobController::class, 'showJobsByManager']);

// محمية: للمدير فقط
Route::middleware('manager')->group(function () {
    Route::post('/manager/jobs', [JobController::class, 'addJob']);
    Route::post('/emanager/jobs/{job_id}', [JobController::class, 'editJob']);
    Route::post('/dmanager/jobs/{job_id}', [JobController::class, 'deletJob']);
    Route::get('/show/manager/jobs', [JobController::class, 'myJobs']);
});

// -------------------- Schedule & Appointment Routes --------------------

//Route::middleware('auth:api')->get('/managerschedule', [UserController::class, 'viewManagerSchedule']);
// Route::middleware('auth:api')->post('/appointmentrequest', [UserController::class, 'requestAppointment']);
// Route::post('add/schedule',[ScheduleController::class,'addSchedule']);
// Route::post('update/schedule/{manager_id}',[ScheduleController::class,'updateSchedule']);
// Route::get('/show/manager/schedule', [ScheduleController::class,'getSchedule']);

//--------------------Manager Schedule---------------------
 Route::post('/add/schedule', [ScheduleController::class, 'addSchedule']);
 Route::post('update/schedule/{id}', [ScheduleController::class, 'updateSchedule']);
Route::get('show/manager/schedule', [ScheduleController::class, 'viewManagerSchedule']);


