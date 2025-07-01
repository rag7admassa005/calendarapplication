<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\SuperadminController;
use Illuminate\Support\Facades\Route;
//super admin محمية بس السوبر ادمن ممكن يصلا
Route::post('/superadmin/login', [SuperadminController::class, 'login']);
Route::post('/managers', [SuperadminController::class, 'addManager'])->middleware('superadmin');
Route::post('/managers/{id}', [SuperadminController::class, 'deleteManager'])->middleware('superadmin');
Route::get('/managers', [SuperadminController::class, 'showAllManagers'])->middleware('superadmin');
Route::get('/managers/{id}', [SuperadminController::class, 'showManager'])->middleware('superadmin');

//Manager غير محمية لانها مسارات دخول وتحقق
Route::post('/manager/set-password', [ManagerController::class, 'setManagerPassword']);
Route::post('/manager/login', [ManagerController::class, 'managerLogin']);
Route::post('/manager/verify-code', [ManagerController::class, 'resendVerificationCode']);

// Job CRUD محمية بس المدير بيقدر يصلا
Route::post('/manager/jobs', [JobController::class, 'addJob'])->middleware('manager');
Route::post('/emanager/jobs/{job_id}', [JobController::class, 'editJob'])->middleware('manager');
Route::post('/dmanager/jobs/{job_id}', [JobController::class, 'deletJob'])->middleware('manager');
Route::get('/show/manager/jobs', [JobController::class, 'myJobs'])->middleware('manager');
//  عام (متاح للمستخدمين لعرض أعمال مدير معين)
Route::get('/managers/{manager_id}/jobs', [JobController::class, 'showJobsByManager']);
