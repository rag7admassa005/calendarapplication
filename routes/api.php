<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\SuperadminController;
use Illuminate\Support\Facades\Route;
//super admin
Route::post('/managers', [SuperadminController::class, 'addManager']);
Route::post('/managers/{id}', [SuperadminController::class, 'deleteManager']);
Route::get('/managers', [SuperadminController::class, 'showAllManagers']);
Route::get('/managers/{id}', [SuperadminController::class, 'showManager']);
//Manager
Route::post('/manager/set-password', [ManagerController::class, 'setManagerPassword']);
Route::post('/manager/login', [ManagerController::class, 'managerLogin']);
Route::post('/manager/verify-code', [ManagerController::class, 'resendVerificationCode']);
