<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);  // تسجيل حساب جديد
Route::post('/verify-code/{id}', [AuthController::class, 'verifyCode']);
Route::post('/resend-verification-code/{id}', [AuthController::class, 'resendVerificationCode']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/profile', [AuthController::class, 'viewProfile'])->middleware('owns-profile');
Route::post('/update/profile', [AuthController::class, 'updateProfile'])->middleware('owns-profile');
Route::post('/reset/password',[AuthController::class,'resetPassword'])->middleware('owns-profile');
Route::post('/forget/password',[AuthController::class,'forgetPassword']);
Route::post('/confirm/resetcode',[AuthController::class,'confirmResetCode']);
