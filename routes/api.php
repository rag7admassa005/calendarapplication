<?php

use App\Http\Controllers\AssistantController;
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
Route::get('/managers-list',[AuthController::class,'listManagers']);
Route::post('/forgotpassword', [AuthController::class, 'forgetPassword']);
Route::post('/resetcode', [AuthController::class, 'confirmResetCode']);
Route::middleware('auth:api')->get('/viewProfile', [AuthController::class, 'viewProfile']);
Route::middleware('auth:api')->post('/updateProfile', [AuthController::class, 'updateProfile']);
Route::middleware('auth:api')->delete('/logout', [AuthController::class, 'logout']);

// -------------------- Superadmin Routes --------------------

Route::post('/superadmin/login', [SuperadminController::class, 'adminLogin']);
Route::middleware('superadmin')->group(function () {
    Route::get('/managers/sections',[SuperadminController::class,'listSectionsManager']);
    Route::post('/managers', [SuperadminController::class, 'addManager']);
    Route::post('/managers/{id}', [SuperadminController::class, 'deleteManager']);
    Route::get('/managers', [SuperadminController::class, 'showAllManagers']);
    Route::get('/managers/{id}', [SuperadminController::class, 'showManager']);
});

// -------------------- Manager Routes --------------------

Route::post('/manager/set-password', [ManagerController::class, 'setManagerPassword']);
Route::post('/manager/verify-code', [ManagerController::class, 'resendVerificationCode']);
Route::get('/manager/set-users', [ManagerController::class, 'getAvailableUsers']);
Route::post('/manager/assignuser', [ManagerController::class, 'assignUserToManager']);


// -------------------- Job Routes --------------------

// عامة: يمكن للمستخدم رؤية الاقسام وكل قسم ووظيفته
Route::get('/sections', [JobController::class, 'listSections']);
Route::get('/jobs/{id}',[JobController::class,'getJobsBySection']);

// محمية: للمدير فقط
// Route::middleware('manager')->group(function () {
//      Route::post('/manager/jobs', [JobController::class, 'addJob']);
//      Route::post('/emanager/jobs/{job_id}', [JobController::class, 'editJob']);
//      Route::post('/dmanager/jobs/{job_id}', [JobController::class, 'deletJob']);
//      Route::get('/show/manager/jobs', [JobController::class, 'myJobs']);
// });

// -------------------- Schedule & Appointment Routes --------------------

// راوتات المستخدم لحجز المواعيد والتعامل مع الدعوات
Route::middleware('auth:api')->get('/managerschedule', [UserController::class, 'viewManagerSchedule']);
Route::middleware('auth:api')->post('/appointmentrequest', [UserController::class, 'requestAppointment']);
Route::middleware('auth:api')->post('/appointments/{id}/cancel', [UserController::class, 'cancelAppointment']);
Route::middleware('auth:api')->post('/appointments/{id}/reschedule', [UserController::class, 'rescheduleAppointment']);
Route::middleware('auth:api')->get('/my-invitations', [UserController::class, 'myInvitations']);
Route::post('/invitations/{id}/respond', [UserController::class, 'respondToInvitation']);
Route::post('/invitations/{id}/cancel-response', [UserController::class, 'cancelInvitationResponse']);
Route::middleware('auth:api')->post('/group-appointments/request', [UserController::class, 'requestGroupAppointment']);
Route::middleware('auth:api')->get('/users', [UserController::class, 'AllUsers']);
Route::middleware('auth:api')->get('/my-appointments', [UserController::class, 'AllAppointments']);

// راوتات المدير لإدارة الجدول الزمني
Route::middleware('manager')->group(function () {
    Route::post('/add/schedule', [ScheduleController::class, 'addSchedule']);
    Route::post('/update/schedule/{id}', [ScheduleController::class, 'updateSchedule']);
    Route::get('/show/manager/schedule', [ScheduleController::class, 'viewManagerSchedule']);
});

Route::middleware(['manager_or_assistant'])->group(function () {
    Route::get('show/requests', [ManageAppointmentController::class, 'showAppointmentRequests']);
    Route::post('appointments/approve/{request_id}', [ManageAppointmentController::class, 'approveAppointmentRequest']);
    Route::post('appointments/reschedule/{id}', [ManageAppointmentController::class, 'rescheduleAppointmentRequest']);
    Route::post('/appointments/cancel/{id}', [ManageAppointmentController::class, 'cancelAppointmentRequest']);
    Route::get('/manager/users', [ManageAppointmentController::class,'getUsers']);
    Route::post('/manager/appointments/invite-existing', [ManageAppointmentController::class, 'inviteUserToAppointment']);
    Route::get('/manager/invitations', [ManageAppointmentController::class, 'getSentInvitations']);
    Route::post('/manager/appointment-notes', [ManageAppointmentController::class, 'addNote']);
    Route::get('/manager/appointment-notes/{appointmentId}', [ManageAppointmentController::class, 'getNotes']);
    Route::post('editnote/{noteId}', [ManageAppointmentController::class, 'updateNote']);
    Route::post('deletenote/{noteId}', [ManageAppointmentController::class, 'deleteNote']);
    Route::get('/getnotes', [ManageAppointmentController::class, 'getAllNotes']);
});

// -------------------- Assistant Routes  --------------------

Route::middleware('manager')->group(function () {
    Route::post('/assign-assistant', [AssistantController::class, 'assignAssistant']);
    Route::post('/assign-permissions', [AssistantController::class, 'assignPermissions']);
    Route::get('/my-assistants', [AssistantController::class, 'getMyAssistants']);
    Route::delete('/assistants/{id}', [AssistantController::class, 'deleteMyAssistant']);
    Route::post('/remove-all-permissions', [AssistantController::class, 'removeAllPermissions']);
    Route::post('/remove-permissions', [AssistantController::class, 'removePermissions']);
});