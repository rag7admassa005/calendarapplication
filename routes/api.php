<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verifyCode/{user_id}', [AuthController::class, 'verifyCode']);
Route::post('/resendCode/{user_id}', [AuthController::class, 'resendVerificationCode']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:api')->get('/viewProfile',[AuthController::class,'viewProfile']);
Route::middleware('auth:api')->post('/updateProfile', [AuthController::class, 'updateProfile']);
Route::post('/forgotpassword', [AuthController::class, 'forgetPassword']);
Route::post('/resetcode', [AuthController::class, 'confirmResetCode']);
Route::middleware('auth:api')->delete('/logout', [AuthController::class, 'logout']);


Route::middleware('auth:api')->get('/managerschedule', [UserController::class, 'viewManagerSchedule']);
//Route::middleware('auth:api')->post('/appointmentrequest', [UserController::class, 'requestAppointment']);
