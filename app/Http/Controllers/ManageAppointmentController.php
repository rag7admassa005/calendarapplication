<?php

namespace App\Http\Controllers;

use App\Models\AppointmentRequest;

use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\AssistantActivity;
use App\Models\Invitation;
use App\Models\Job;
use App\Models\Manager;
use App\Models\Permission;
use App\Models\Schedule ;
use App\Models\User;
use App\Notifications\AppointmentApprovedNotification;
use App\Notifications\AppointmentInvitation;
use App\Notifications\AppointmentRejected;
use App\Notifications\AppointmentRescheduled;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ManageAppointmentController extends Controller
{
    // عرض الطلبات مع امكانية التصفية
 public function showAppointmentRequests(Request $request)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // إذا كان المستخدم مساعد
    if ($assistant) {
        // جلب المدير المرتبط بهذا المساعد
        $linkedManager = $assistant->manager;

        if (!$linkedManager) {
            return response()->json(['message' => 'No manager linked to this assistant'], 400);
        }

        // نتحقق أن المساعد يحاول الوصول لمستخدمين مرتبطين بمديره فقط
        if ($manager && $manager->id !== $linkedManager->id) {
            return response()->json(['message' => 'Assistant not linked to this manager'], 403);
        }

        // نثبت المدير المرتبط فعليًا
        $manager = $linkedManager;

        // التحقق من الصلاحية
        $permission = Permission::where('name', 'view_appointment_requests')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    // التحقق من الفلترة
    $validator = Validator::make($request->all(), [
        'status' => 'nullable|in:pending,approved,rejected,rescheduled,cancelled',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // استعلام الطلبات
    $query = AppointmentRequest::with([
        'user:id,first_name,last_name,email',
        'reviewedBy',
        'invitations.invitedUser:id,first_name,last_name,email'
    ])->where('manager_id', $manager->id);

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    $appointmentRequests = $query->get()->map(function ($appointment) {
        return [
            'id' => $appointment->id,
            'preferred_date' => $appointment->preferred_date,
            'preferred_start_time' => $appointment->preferred_start_time,
            'end_time' => $appointment->preferred_end_time,
            'duration' => $appointment->preferred_duration,
            'status' => $appointment->status,
            'reason' => $appointment->reason,
            'requested_at' => $appointment->requested_at,
            'user' => $appointment->user ? [
                'id' => $appointment->user->id,
                'first_name' => $appointment->user->first_name,
                'last_name' => $appointment->user->last_name,
                'email' => $appointment->user->email,
            ] : null,
            'reviewed_by' => $appointment->reviewedBy ? [
                'type' => class_basename($appointment->reviewed_by_type),
                'id' => $appointment->reviewedBy->id,
                'name' => $appointment->reviewedBy->name ?? null,
            ] : null,
            'invitations' => $appointment->invitations->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'status' => $inv->status,
                    'sent_at' => $inv->sent_at,
                    'responded_at' => $inv->responded_at,
                    'invited_user' => $inv->invitedUser ? [
                        'id' => $inv->invitedUser->id,
                        'first_name' => $inv->invitedUser->first_name,
                        'last_name' => $inv->invitedUser->last_name,
                        'email' => $inv->invitedUser->email,
                    ] : null,
                ];
            }),
        ];
    });

    return response()->json([
        'message' => 'Appointment requests retrieved successfully.',
        'data' => $appointmentRequests
    ], 200);
}



 public function approveAppointmentRequest($id)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    // تحديد الجهة المنفذة
    if (!$manager && !$assistant) {
        return response(['message' => 'Unauthorized'], 401);
    }

    $request = AppointmentRequest::findOrFail($id);

    // جلب المدير المسؤول عن الطلب
    $ownerManager = Manager::find($request->manager_id);
    if (!$ownerManager) {
        return response()->json(['message' => 'Manager not found'], 404);
    }

    // التحقق من صلاحية المساعد إن وجد
    if ($assistant) {
        // التحقق أن هذا المساعد يتبع للمدير صاحب الطلب
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        // التحقق من صلاحية تنفيذ الإجراء
        $permission = Permission::where('name', 'accept_appointment')->first();
        if (!$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    // المدير أو المساعد يستطيع تنفيذ التابع الآن
    // لا تسمح بقبول الطلب أكثر من مرة
    if ($request->status === 'approved') {
        return response()->json(['message' => 'This appointment request has already been approved.'], 400);
    }

    // التحقق من التداخل الزمني
    $overlapping = Appointment::where('manager_id', $ownerManager->id)
        ->where('date', $request->preferred_date)
        ->where(function ($query) use ($request) {
            $query->whereBetween('start_time', [$request->preferred_start_time, $request->preferred_end_time])
                ->orWhereBetween('end_time', [$request->preferred_start_time, $request->preferred_end_time])
                ->orWhere(function ($q) use ($request) {
                    $q->where('start_time', '<=', $request->preferred_start_time)
                      ->where('end_time', '>=', $request->preferred_end_time);
                });
        })
        ->exists();

    if ($overlapping) {
        return response()->json(['message' => 'The requested appointment time overlaps with an existing appointment.'], 409);
    }

    // إنشاء الموعد
    $appointment = Appointment::create([
        'date' => $request->preferred_date,
        'start_time' => $request->preferred_start_time,
        'end_time' => $request->preferred_end_time,
        'duration' => $request->preferred_duration,
        'status' => 'approved',
        'manager_id' => $ownerManager->id,
    ]);

    // ربط صاحب الطلب والمستخدمين المقبولين
    $appointment->users()->syncWithoutDetaching([$request->user_id]);

    $acceptedUsers = Invitation::where('related_to_type', get_class($request))
        ->where('related_to_id', $request->id)
        ->where('status', 'accepted')
        ->pluck('invited_user_id')
        ->toArray();

    if (!empty($acceptedUsers)) {
        $appointment->users()->syncWithoutDetaching($acceptedUsers);
    }

    // تحديث حالة الطلب وربط المراجع
    $reviewedBy = $manager ?: $assistant;
    $request->update([
        'status' => 'approved',
        'reviewed_by_type' => get_class($reviewedBy),
        'reviewed_by_id' => $reviewedBy->id,
    ]);

      // تحميل بيانات إضافية
    $request->load('user');
    $invitedUsers = \App\Models\User::whereIn('id', $acceptedUsers)->get();


    // إشعارات
    $request->user->notify(new AppointmentApprovedNotification($appointment));

 // إشعار المستخدمين المدعوين
    foreach ($invitedUsers as $user) {
        $user->notify(new AppointmentRescheduled($appointment));
    }

    // 📝 تسجيل تتبع النشاط للمساعد فقط
    if ($assistant) {
        AssistantActivity::create([
            'assistant_id'    => $assistant->id,
    'permission_id'   => $permission->id,
    'related_to_type' => AppointmentRequest::class,
    'related_to_id'   =>  $request->id,
    'executed_at'     => now(),
        ]);
    }

    return response()->json([
        'message' => 'Appointment approved and scheduled.',
        'appointment' => $appointment,
        'appointment_request' => [
            'id' => $request->id,
            'status' => $request->status,
            'created_by' => $request->user,
            'accepted_invited_users' => $invitedUsers
        ]
    ]);
}
public function rescheduleAppointmentRequest(Request $request, $id)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $appointmentRequest = AppointmentRequest::findOrFail($id);

    $ownerManager = Manager::find($appointmentRequest->manager_id);
    if (!$ownerManager) {
        return response()->json(['message' => 'Manager not found'], 404);
    }

    if ($assistant) {
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        $permission = Permission::where('name', 'reschedule_appointment')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    $validator = Validator::make($request->all(),[
        'date' => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
    ]);

    $dayOfWeek = strtolower(Carbon::parse($request->date)->format('l'));

    $schedule = Schedule::where('manager_id', $ownerManager->id)
        ->where('day_of_week', $dayOfWeek)
        ->where('is_available', true)
        ->first();

    if (!$schedule) {
        return response()->json(['message' => 'This day is not available for scheduling.'], 422);
    }

    $start = Carbon::createFromFormat('H:i', $request->start_time);
    $scheduleStart = Carbon::createFromFormat('H:i:s', $schedule->start_time);
    $scheduleEnd = Carbon::createFromFormat('H:i:s', $schedule->end_time);

    if ($start->lt($scheduleStart) || $start->gte($scheduleEnd)) {
        return response()->json(['message' => 'The selected time is outside the manager\'s available hours.'], 422);
    }

    $availableDurations = [$schedule->meeting_duration_1, $schedule->meeting_duration_2];
    $duration = null;
    $end = null;

    foreach ($availableDurations as $d) {
        $endCandidate = $start->copy()->addMinutes($d);
        if ($endCandidate->lte($scheduleEnd)) {
            $duration = $d;
            $end = $endCandidate;
            break;
        }
    }

    if (!$duration || !$end) {
        return response()->json(['message' => 'Invalid time or duration exceeds available slot.'], 422);
    }

    // تحقق من التداخل
    $overlapping = Appointment::where('manager_id', $ownerManager->id)
        ->where('date', $request->date)
        ->where(function ($query) use ($request, $end) {
            $query->whereBetween('start_time', [$request->start_time, $end->format('H:i')])
                ->orWhereBetween('end_time', [$request->date, $end->format('H:i')])
                ->orWhere(function ($q) use ($request, $end) {
                    $q->where('start_time', '<=', $request->start_time)
                      ->where('end_time', '>=', $end->format('H:i'));
                });
        })
        ->exists();

    if ($overlapping) {
        return response()->json(['message' => 'The selected time overlaps with an existing appointment.'], 409);
    }

    $appointment = Appointment::create([
        'date' => $request->date,
        'start_time' => $request->start_time,
        'end_time' => $end->format('H:i'),
        'duration' => $duration,
        'status' => 'rescheduled',
        'manager_id' => $ownerManager->id,
    ]);

    $appointment->users()->syncWithoutDetaching([$appointmentRequest->user_id]);

    $acceptedUserIds = Invitation::where('related_to_type', get_class($appointmentRequest))
        ->where('related_to_id', $appointmentRequest->id)
        ->where('status', 'accepted')
        ->pluck('invited_user_id')
        ->toArray();

    if (!empty($acceptedUserIds)) {
        $appointment->users()->syncWithoutDetaching($acceptedUserIds);
    }

    $reviewedBy = $manager ?: $assistant;
    $appointmentRequest->update([
        'status' => 'rescheduled',
        'reviewed_by_type' => get_class($reviewedBy),
        'reviewed_by_id' => $reviewedBy->id,
    ]);

    $appointmentRequest->load('user');
    $invitedUsers = User::whereIn('id', $acceptedUserIds)->get();

    $appointmentRequest->user->notify(new AppointmentRescheduled($appointment));
    foreach ($invitedUsers as $user) {
        $user->notify(new AppointmentRescheduled($appointment));
    }
 
    

    if ($assistant) {
        AssistantActivity::create([
            'assistant_id'    => $assistant->id,
    'permission_id'   => $permission->id,
    'related_to_type' => AppointmentRequest::class,
    'related_to_id'   => $appointmentRequest->id,
    'executed_at'     => now(),
        ]);
    }

    return response()->json([
        'message' => 'Appointment rescheduled successfully.',
        'appointment' => $appointment,
        'appointment_request' => [
            'id' => $appointmentRequest->id,
            'status' => $appointmentRequest->status,
            'created_by' => $appointmentRequest->user,
            'accepted_invited_users' => $invitedUsers,
        ]
    ]);
}
  public function cancelAppointmentRequest($id)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    // التحقق من الجهة المنفذة
    if (!$manager && !$assistant) {
        return response(['message' => 'Unauthorized'], 401);
    }

    $requestApp = AppointmentRequest::findOrFail($id);

    // جلب المدير المسؤول عن الطلب
    $ownerManager = Manager::find($requestApp->manager_id);
    if (!$ownerManager) {
        return response()->json(['message' => 'Manager not found'], 404);
    }

    // تحقق من صلاحية المساعد
    if ($assistant) {
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        $permission = Permission::where('name', 'reject_appointment')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    // لا تسمح بالرفض المتكرر
    if ($requestApp->status === 'rejected') {
        return response()->json(['message' => 'This appointment request has already been rejected.'], 400);
    }

    // المستخدمين يلي وافقوا على الحضور
    $acceptedUsers = Invitation::where('related_to_type', get_class($requestApp))
        ->where('related_to_id', $requestApp->id)
        ->where('status', 'accepted')
        ->pluck('invited_user_id')
        ->toArray();
        

    // تحديث حالة الطلب
    $reviewedBy = $manager ?: $assistant;
    $requestApp->update([
        'status' => 'rejected',
        'reviewed_by_type' => get_class($reviewedBy),
        'reviewed_by_id' => $reviewedBy->id,
    ]);

    // إشعار من أنشأ الطلب
    $requestApp->user->notify(new AppointmentRejected());

    // إشعار المستخدمين المدعوين يلي وافقوا
    $invitedUsers = \App\Models\User::whereIn('id', $acceptedUsers)->get();
    foreach ($invitedUsers as $user) {
        $user->notify(new AppointmentRejected());
    }

    // حذف الموعد إذا تم إنشاؤه مسبقاً
    $existingAppointment = Appointment::where('date', $requestApp->preferred_date)
    ->where('start_time', $requestApp->preferred_start_time)
    ->whereHas('users', function ($query) use ($requestApp) {
        $query->where('users.id', $requestApp->user_id);
    })->first();

    if ($existingAppointment) {
    $existingAppointment->users()->detach(); // حذف روابط المستخدمين
    $existingAppointment->delete(); // حذف الموعد نفسه
}

    


    // تسجيل النشاط للمساعد فقط
    if ($assistant) {
        AssistantActivity::create([
            'assistant_id'    => $assistant->id,
    'permission_id'   => $permission->id,
    'related_to_type' => AppointmentRequest::class,
    'related_to_id'   => $requestApp->id,
    'executed_at'     => now(),
        ]);
    }

    return response()->json([
        'message' => 'Appointment request rejected',
        'appointment_request' => [
            'id' => $requestApp->id,
            'status' => $requestApp->status,
            'created_by' => $requestApp->user,
            'accepted_invited_users' => $invitedUsers,
        ]
    ]);
}

    // عرض جميع المستخدمين المرتبطين بهالمدير وتصفيتهم حسب العمل 
    public function getUsers(Request $request)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // إذا كان المستخدم مساعد
    if ($assistant) {
        // جلب المدير المرتبط بهذا المساعد
        $linkedManager = $assistant->manager;

        if (!$linkedManager) {
            return response()->json(['message' => 'No manager linked to this assistant'], 400);
        }

        // نتحقق أن المساعد يحاول الوصول لمستخدمين مرتبطين بمديره فقط
        if ($manager && $manager->id !== $linkedManager->id) {
            return response()->json(['message' => 'Assistant not linked to this manager'], 403);
        }

        // نثبت المدير المرتبط فعليًا
        $manager = $linkedManager;

        // التحقق من الصلاحية
        $permission = Permission::where('name', 'view_users')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    // التحقق من مدخلات الطلب
    $validator = Validator::make($request->all(), [
        'job_id' => 'nullable|integer|exists:jobs,id',
    ]);

    if ($validator->fails()) {
        return response(['errors' => $validator->errors()], 422);
    }

    // بدء الاستعلام بجلب المستخدمين المرتبطين بالمدير
    $query = User::where('manager_id', $manager->id);

    // إذا تم تمرير job_id
    if ($request->has('job_id')) {
        $query->where('job_id', $request->job_id);
    }

    $users = $query->get();

    return response([
        'users' => $users
    ]);
}
public function inviteUserToAppointment(Request $request)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response(['message' => 'Unauthorized'], 401);
    }

    // تحديد المدير المالك
    if ($assistant) {
        // تحقق أن المساعد له مدير
        if (!$assistant->manager) {
            return response()->json(['message' => 'This assistant does not belong to any manager'], 403);
        }

        $ownerManager = $assistant->manager;

        // تحقق من صلاحية المساعد لإرسال الدعوات
        $permission = Permission::where('name', 'invite_users')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    } else {
        $ownerManager = $manager;
    }

    // تحقق من المدخلات
    $validator = Validator::make($request->all(), [
        'user_id'       => 'required|exists:users,id',
        'appointment_id'=> 'nullable|exists:appointments,id',
        'date'          => 'required_without:appointment_id|date',
        'start_time'    => 'required_without:appointment_id|date_format:H:i',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // تحقق أن المستخدم تابع لنفس المدير
    $user = User::find($request->user_id);
    $belongsToManager = $ownerManager->users()->where('users.id', $user->id)->exists();
    if (!$belongsToManager) {
        return response()->json(['message' => 'User does not belong to this manager'], 403);
    }

    // ===== الحالة 1: موعد موجود =====
if ($request->filled('appointment_id')) {
    $appointment = Appointment::find($request->appointment_id);

    if ($appointment->manager_id !== $ownerManager->id) {
        return response()->json(['message' => 'Unauthorized - not your appointment'], 403);
    }

    // تحقق إذا المستخدم موجود أساسًا في الموعد
    $alreadyParticipant = DB::table('appointment_user')
        ->where('appointment_id', $appointment->id)
        ->where('user_id', $user->id)
        ->exists();

    if ($alreadyParticipant) {
        return response()->json(['message' => 'User is already part of this appointment'], 409);
    }

    // تحقق إذا المستخدم مدعو مسبقًا
    $alreadyInvited = Invitation::where('related_to_type', Appointment::class)
        ->where('related_to_id', $appointment->id)
        ->where('invited_user_id', $user->id)
        ->exists();

    if ($alreadyInvited) {
        return response()->json(['message' => 'User already invited to this appointment'], 409);
    }
}

    // ===== الحالة 2: إنشاء موعد جديد =====
    else {
        $date = $request->date;
        $start_time = $request->start_time;
        $day = strtolower(Carbon::parse($date)->format('l'));

        $schedule = Schedule::where('manager_id', $ownerManager->id)
            ->where('day_of_week', $day)
            ->where('is_available', true)
            ->where('start_time', '<=', $start_time)
            ->where('end_time', '>', $start_time)
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'The manager is not available at this time.'], 400);
        }

        $duration = $schedule->meeting_duration_1;
        $end_time_obj = Carbon::createFromFormat('H:i', $start_time)->addMinutes($duration);
        $end_time = $end_time_obj->format('H:i');

        if ($end_time > $schedule->end_time) {
            return response()->json(['message' => 'Appointment exceeds available schedule.'], 400);
        }

        $existingAppointment = Appointment::where('manager_id', $ownerManager->id)
            ->where('date', $date)
            ->where('start_time', $start_time)
            ->where('end_time', $end_time)
            ->where('duration', $duration)
            ->first();

        if ($existingAppointment) {
            $alreadyInvited = Invitation::where('related_to_type', Appointment::class)
                ->where('related_to_id', $existingAppointment->id)
                ->where('invited_user_id', $user->id)
                ->exists();

            if ($alreadyInvited) {
                return response()->json(['message' => 'User already invited to this identical appointment'], 409);
            }

            $appointment = $existingAppointment;
        } else {
            $appointment = Appointment::create([
                'manager_id'   => $ownerManager->id,
                'assistant_id' => $assistant?->id,
                'date'         => $date,
                'start_time'   => $start_time,
                'end_time'     => $end_time,
                'duration'     => $duration,
                'status'       => 'pending',
            ]);
        }
    }

    // إنشاء الدعوة
    $inviter = $manager ?: $assistant;
    $invitation = Invitation::create([
        'related_to_type' => Appointment::class,
        'related_to_id'   => $appointment->id,
        'invited_user_id' => $user->id,
        'invited_by_type' => get_class($inviter),
        'invited_by_id'   => $inviter->id,
        'status'          => 'pending',
        'sent_at'         => now(),
    ]);

    $user->notify(new AppointmentInvitation($appointment, $ownerManager));
      if ($assistant) {
AssistantActivity::create([
    'assistant_id'    => $assistant->id,
    'permission_id'   => $permission->id,
    'related_to_type' => Appointment::class,          // أو AppointmentRequest::class
    'related_to_id'   => $appointment->id,
    'executed_at'     => now(),
]);
      }
    return response()->json([
        'message'     => 'Invitation sent successfully.',
        'appointment' => $appointment,
        'invitation'  => $invitation,
    ]);
}


public function getSentInvitations(Request $request)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // حالة المساعد
    if ($assistant) {
        // جلب المدير المرتبط بالمساعد
        $linkedManager = $assistant->manager;

        if (!$linkedManager) {
            return response()->json(['message' => 'No manager linked to this assistant'], 400);
        }

        // التحقق من صلاحية المساعد
        $permission = Permission::where('name', 'view_invitations')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        // جلب IDs كل المساعدين المرتبطين بنفس المدير
        $assistantIds = $linkedManager->assistants()->pluck('id')->toArray();

        // جلب الدعوات التي أرسلها المدير أو أي مساعد تابع للمدير
        $invitations = Invitation::where(function ($query) use ($linkedManager, $assistantIds) {
            $query->where(function ($q) use ($linkedManager) {
                $q->where('invited_by_type', get_class($linkedManager))
                  ->where('invited_by_id', $linkedManager->id);
            })
            ->orWhere(function ($q) use ($assistantIds) {
                $q->where('invited_by_type', \App\Models\Assistant::class)
                  ->whereIn('invited_by_id', $assistantIds);
            });
        })
        ->with(['invitedUser:id,first_name,last_name,email', 'relatedTo'])
        ->latest()
        ->get();

        return response()->json(['invitations' => $invitations]);
    }

    // حالة المدير
    if ($manager) {
        // جلب IDs المساعدين التابعين للمدير
        $assistantIds = $manager->assistants()->pluck('id')->toArray();

        // جلب الدعوات التي أرسلها المدير أو أي مساعد تابع له
        $invitations = Invitation::where(function ($query) use ($manager, $assistantIds) {
            $query->where(function ($q) use ($manager) {
                $q->where('invited_by_type', get_class($manager))
                  ->where('invited_by_id', $manager->id);
            })
            ->orWhere(function ($q) use ($assistantIds) {
                $q->where('invited_by_type', \App\Models\Assistant::class)
                  ->whereIn('invited_by_id', $assistantIds);
            });
        })
        ->with(['invitedUser:id,first_name,last_name,email', 'relatedTo'])
        ->latest()
        ->get();

        return response()->json(['invitations' => $invitations]);
    }
}

public function addNote(Request $request)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // تحقق من صحة المدخلات
    $validator = Validator::make($request->all(), [
        'appointment_id' => 'required|exists:appointments,id',
        'notes'          => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $appointment = Appointment::find($request->appointment_id);

    // تحديد المدير المالك للموعد
    $ownerManager = $appointment->manager;

    if ($assistant) {
        // تحقق أن المساعد تابع للمدير
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        // تحقق الصلاحية (مثلاً 'add_notes')
        $permission = Permission::where('name', 'add_notes')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    } else {
        // تحقق أن المدير هو مالك الموعد
        if ($manager->id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized manager'], 403);
        }
    }

    // إنشاء الملاحظة
    $note = AppointmentNote::create([
        'appointment_id' => $appointment->id,
        'author_type'    => $manager ? get_class($manager) : get_class($assistant),
        'author_id'      => $manager ? $manager->id : $assistant->id,
        'notes'         => $request->notes,
    ]);

    // تسجيل نشاط المساعد
    if ($assistant) {
       AssistantActivity::create([
    'assistant_id'    => $assistant->id,
    'permission_id'   => $permission->id,
    'related_to_type' => Appointment::class,          // أو AppointmentRequest::class
    'related_to_id'   => $appointment->id,
    'executed_at'     => now(),
]);
    }

    return response()->json([
        'message' => 'Note added successfully.',
        'note'    => $note,
    ]);
}

//ملاحظات موعد 
public function getAppointmentNotes($appointmentId)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    $appointment = Appointment::findOrFail($appointmentId);

    // حالة المدير: فقط اذا هو صاحب الموعد
    if ($manager) {
        if ($appointment->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }
    // حالة المساعد: لازم يكون تابع لنفس المدير وصلاحية
    elseif ($assistant) {
        $ownerManager = $assistant->manager;
        if (!$ownerManager || $appointment->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // // تحقق صلاحية المساعد
        // $permission = Permission::where('name', 'view_appointment_notes')->first();
        // if (!$permission || !$assistant->permissions->contains($permission->id)) {
        //     return response()->json(['message' => 'Permission denied'], 403);
        // }
    }
    // غير مسجل دخول كمدير أو مساعد
    else {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $notes = AppointmentNote::where('appointment_id', $appointmentId)
        ->with('author') // لعرض اسم المدير أو المساعد
        ->latest()
        ->get();

    return response()->json([
        'appointment_id' => $appointmentId,
        'notes' => $notes
    ]);
}
// edit delete showall
public function updateNote(Request $request, $noteId)
    {
        $request->validate([
            'notes' => 'required|string'
        ]);

        $manager = Auth::guard('manager')->user();
        $assistant = Auth::guard('assistant')->user();
if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
        
        $note = AppointmentNote::findOrFail($noteId);
        $appointment = Appointment::findOrFail($note->appointment_id);

         // تحديد المدير المالك للموعد
    $ownerManager = $appointment->manager;

    if ($assistant) {
        // تحقق أن المساعد تابع للمدير
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        // تحقق الصلاحية (مثلاً 'add_notes')
        $permission = Permission::where('name', 'edit_notes')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    } else {
        // تحقق أن المدير هو مالك الموعد
        if ($manager->id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized manager'], 403);
        }
    }

      $note->update([
        'author_type'    => $manager ? get_class($manager) : get_class($assistant),
        'author_id'      => $manager ? $manager->id : $assistant->id,
        'notes'         => $request->notes,
      ]);
            // سجل النشاط
          if ($assistant) {
       AssistantActivity::create([
    'assistant_id'    => $assistant->id,
    'permission_id'   => $permission->id,
    'related_to_type' => Appointment::class,          // أو AppointmentRequest::class
    'related_to_id'   => $appointment->id,
    'executed_at'     => now(),
]);
    }
       

        return response()->json(['message' => 'Note updated successfully', 'note' => $note]);
    }

    /**
     * حذف ملاحظة
     */
    public function deleteNote($noteId)
    {
        $manager = Auth::guard('manager')->user();
        $assistant = Auth::guard('assistant')->user();

        if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
        $note = AppointmentNote::findOrFail($noteId);
        $appointment = Appointment::findOrFail($note->appointment_id);

         // تحديد المدير المالك للموعد
    $ownerManager = $appointment->manager;

    if ($assistant) {
        // تحقق أن المساعد تابع للمدير
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        // تحقق الصلاحية (مثلاً 'add_notes')
        $permission = Permission::where('name', 'delete_notes')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    } else {
        // تحقق أن المدير هو مالك الموعد
        if ($manager->id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized manager'], 403);
        }
    }

    $note->delete();

    if($assistant){
            AssistantActivity::create([
                'assistant_id' => $assistant->id,
                'permission_id' => $permission->id,
                'related_to_type' => Appointment::class,
                'related_to_id' => $appointment->id,
                'executed_at' => Carbon::now()
            ]);
      
        }
      

        return response()->json(['message' => 'Note deleted successfully']);
    }

    /**
     * جلب كل الملاحظات لكل المواعيد
     */
    // public function getAllNotes()
    // {
    //     $manager = Auth::guard('manager')->user();
    //     $assistant = Auth::guard('assistant')->user();

    //     if (!$manager && !$assistant) {
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }

    //     $managerId = null;

    //     if ($assistant) {
    //         $linkedManager = $assistant->manager;
    //         if (!$linkedManager) {
    //             return response()->json(['message' => 'No linked manager'], 400);
    //         }

           

    //         AssistantActivity::create([
    //             'assistant_id' => $assistant->id,
    //             'permission_id' => $permission->id,
    //             'related_to_type' => 'all_notes',
    //             'related_to_id' => null,
    //             'executed_at' => Carbon::now()
    //         ]);

    //         $managerId = $linkedManager->id;
    //     } else {
    //         $managerId = $manager->id;
    //     }

    //     $notes = AppointmentNote::whereHas('appointment', function ($q) use ($managerId) {
    //         $q->where('manager_id', $managerId);
    //     })
    //         ->with('author')
    //         ->latest()
    //         ->get();

    //     return response()->json(['notes' => $notes]);
    // }

}
