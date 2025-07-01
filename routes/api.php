<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\SuperadminController;
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

Route::post('/managers', [SuperadminController::class, 'addManager']);
Route::post('/managers/{id}', [SuperadminController::class, 'deleteManager']);
Route::get('/managers', [SuperadminController::class, 'showAllManagers']);
Route::get('/managers/{id}', [SuperadminController::class, 'showManager']);

// -------------------- Manager Routes --------------------

Route::post('/manager/set-password', [ManagerController::class, 'setManagerPassword']);
Route::post('/manager/login', [ManagerController::class, 'managerLogin']);
Route::post('/manager/verify-code', [ManagerController::class, 'resendVerificationCode']);