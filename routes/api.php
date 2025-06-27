<?php

use App\Http\Controllers\AuthController;
use Illuminate\Routing\Route;

Route::post('register', [AuthController::class, 'register']);  // تسجيل حساب جديد

Route::post('/resend-verification-code/{id}', [AuthController::class, 'resendVerificationCode']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->get('/profile', [AuthController::class, 'viewProfile']);
