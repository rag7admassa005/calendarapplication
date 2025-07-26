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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ManageAppointmentController extends Controller
{
    // عرض الطلبات مع امكانية التصفية
   public function showAppointmentRequests(Request $request)
{
    $validator = Validator::make($request->all(), [
        'status' => 'nullable|in:pending,approved,rejected,rescheduled,cancelled',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $manager = Auth::guard('manager')->user();
    if (!$manager) {
        return response()->json(['message' => 'Manager not found'], 404);
    }

    $query = AppointmentRequest::with([
        'user:id,first_name,last_name,email',
        'reviewedBy',
        'invitations.invitedUser:id,first_name,last_name,email'
    ])->where('manager_id', $manager->id);

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    $appointmentRequests = $query->get()->map(function ($request) {
        return [
            'id' => $request->id,
            'preferred_date' => $request->preferred_date,
            'preferred_start_time' => $request->preferred_start_time,
            'end_time' => $request->preferred_end_time,
            'duration' => $request->preferred_duration,
            'status' => $request->status,
            'reason' => $request->reason,
            'requested_at' => $request->requested_at,
            'user' => [
                'id' => $request->user->id,
                'first_name' => $request->user->first_name,
                'last_name' => $request->user->last_name,
                'email' => $request->user->email,
            ],
            'reviewed_by' => $request->reviewedBy ? [
                'type' => class_basename($request->reviewed_by_type),
                'id' => $request->reviewedBy->id,
                'name' => $request->reviewedBy->name ?? null,  // تأكد من وجود هذا الحقل في جدول المراجع (Manager أو Assistant)
            ] : null,
            'invitations' => $request->invitations->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'status' => $inv->status,
                    'sent_at' => $inv->sent_at,
                    'responded_at' => $inv->responded_at,
                    'invited_user' => [
                        'id' => $inv->invitedUser->id,
                        'first_name' => $inv->invitedUser->first_name,
                        'last_name' => $inv->invitedUser->last_name,
                        'email' => $inv->invitedUser->email,
                    ],
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
            'assistant_id' => $assistant->id,
            'permission_id' => $permission->id,
            'appointment_id' => $appointment->id,
            'executed_at' => now(),
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
    $appointmentRequest = AppointmentRequest::findOrFail($id);

    // تحقق من ملكية المدير للطلب
    if ($appointmentRequest->manager_id !== $manager->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // التحقق من البيانات الجديدة
    $validated = $request->validate([
        'date' => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
    ]);

    // حساب المدة (بالدقائق)
    $start = \Carbon\Carbon::createFromFormat('H:i', $validated['start_time']);
    $end = \Carbon\Carbon::createFromFormat('H:i', $validated['end_time']);
    $duration = $start->diffInMinutes($end);

    // تحقق من التداخل الزمني مع مواعيد المدير الموجودة (باستثناء الموعد الحالي إن وجد)
    $overlapping = Appointment::where('manager_id', $manager->id)
        ->where('date', $validated['date'])
        ->where(function ($query) use ($validated) {
            $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                ->orWhere(function ($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                });
        })
        ->exists();

    if ($overlapping) {
        return response()->json([
            'message' => 'The requested appointment time overlaps with an existing appointment.'
        ], 409);
    }

    // إنشاء موعد جديد (الجدول appointments)
    $appointment = Appointment::create([
        'date' => $validated['date'],
        'start_time' => $validated['start_time'],
        'end_time' => $validated['end_time'],
        'duration' => $duration,
        'status' => 'rescheduled',
        'manager_id' => $manager->id,
    ]);

    // ربط المستخدم الأساسي (صاحب طلب الموعد)
    $appointment->users()->syncWithoutDetaching([$appointmentRequest->user_id]);

    // جلب المستخدمين المدعوين الذين قبلوا الدعوة
    $acceptedUserIds = Invitation::where('related_to_type', get_class($appointmentRequest))
        ->where('related_to_id', $appointmentRequest->id)
        ->where('status', 'accepted')
        ->pluck('invited_user_id')
        ->toArray();

    if (!empty($acceptedUserIds)) {
        $appointment->users()->syncWithoutDetaching($acceptedUserIds);
    }

    // تحديث حالة طلب الموعد
    $appointmentRequest->update([
        'status' => 'rescheduled',
        'reviewed_by_type' => get_class($manager),
        'reviewed_by_id' => $manager->id,
    ]);

    // تحميل بيانات إضافية
    $appointmentRequest->load('user');
    $invitedUsers = \App\Models\User::whereIn('id', $acceptedUserIds)->get();

    // إشعار صاحب الطلب
    $appointmentRequest->user->notify(new AppointmentRescheduled($appointment));

    // إشعار المستخدمين المدعوين
    foreach ($invitedUsers as $user) {
        $user->notify(new AppointmentRescheduled($appointment));
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
        $request = AppointmentRequest::findOrFail($id);
        if (!$request) {
            return response()->json(['message' => 'Appointment request not found'], 404);
        }
        // تحقق من ملكية المدير للطلب
        if ($request->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $acceptedUsers = Invitation::where('related_to_type', get_class($request))
            ->where('related_to_id', $request->id)
            ->where('status', 'accepted')
            ->pluck('invited_user_id')
            ->toArray();



        $request->update([
            'status' => 'rejected',
            'reviewed_by_type' => get_class($manager),
            'reviewed_by_id' => $manager->id,
        ]);

        $invitedUsers = \App\Models\User::whereIn('id', $acceptedUsers)->get();
        $request->user->notify(new AppointmentRejected());

        // إرسال إشعار للمستخدمين المدعوين
        foreach ($invitedUsers as $user) {
            $user->notify(new AppointmentRejected());
        }
        return response()->json([
            'message' => 'Appointment request rejected',
            'appointment_request' => [
                'id' => $request->id,
                'status' => $request->status,
                'created_by' => $request->user, // بيانات من أنشأ الطلب
                'accepted_invited_users' => $invitedUsers // المستخدمين يلي رح يحضروا
            ]

        ]);
    }

    // عرض جميع المستخدمين المرتبطين بهالمدير وتصفيتهم حسب العمل 
    public function getUsers(Request $request)
    {
        $manager = Auth::guard('manager')->user();
        if (!$manager) {
            return response(['message' => 'manager is not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|integer|exists:jobs,id',
        ]);

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()], 422);
        }

        // بدء الاستعلام بجلب المستخدمين المرتبطين بهذا المدير فقط
        $query = User::where('manager_id', $manager->id);

        // إذا تم تمرير job_id
        if ($request->has('job_id')) {
            $jobId = $request->job_id;

            // تحقق أن الوظيفة فعلاً تتبع لهذا المدير
            $jobBelongsToManager = Job::where('id', $jobId)
                ->where('manager_id', $manager->id)
                ->exists();

            if (!$jobBelongsToManager) {
                return response(['message' => 'This job does not belong to you'], 403);
            }

            // تطبيق التصفية حسب الوظيفة
            $query->where('job_id', $jobId);
        }

        $users = $query->get();

        return response([
            'users' => $users
        ]);
    }
public function inviteUserToAppointment(Request $request)
{
    $manager = Auth::guard('manager')->user();

    if (!$manager) {
        return response(['message' => 'Manager not found'], 404);
    }

    $validator = Validator::make($request->all(), [
        'user_id' => 'required|exists:users,id',
        'appointment_id' => 'nullable|exists:appointments,id',
        'date' => 'required_without:appointment_id|date',
        'start_time' => 'required_without:appointment_id|date_format:H:i',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $user = User::find($request->user_id);
    if (!$user) {
        return response(['message' => 'User not found'], 404);
    }

    // تحقق أن المستخدم يتبع للمدير
    $isUserBelongsToManager = $manager->users()->where('users.id', $user->id)->exists();
    if (!$isUserBelongsToManager) {
        return response()->json(['message' => 'User does not belong to this manager'], 403);
    }

    // ==== الحالة 1: الموعد موجود مسبقاً ====
    if ($request->filled('appointment_id')) {
        $appointment = Appointment::find($request->appointment_id);

        if ($appointment->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized - not your appointment'], 403);
        }

        $alreadyInvited = Invitation::where('related_to_type', get_class($appointment))
            ->where('related_to_id', $appointment->id)
            ->where('invited_user_id', $user->id)
            ->exists();

        if ($alreadyInvited) {
            return response()->json(['message' => 'User already invited to this appointment'], 409);
        }
    } else {
        // ==== الحالة 2: إنشاء موعد جديد ====

        $date = $request->date;
        $start_time = $request->start_time;
        $day = strtolower(Carbon::parse($date)->format('l'));

        // جلب جدول المواعيد للمدير
        $schedule = Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $day)
            ->where('is_available', true)
            ->where('start_time', '<=', $start_time)
            ->where('end_time', '>', $start_time)
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'The manager is not available at this time.'], 400);
        }

        // حساب وقت الانتهاء حسب المدة من الجدولة
        $duration = $schedule->meeting_duration_1;
        $end_time_obj = Carbon::createFromFormat('H:i', $start_time)->addMinutes($duration);
        $end_time = $end_time_obj->format('H:i');

        // تحقق أن نهاية الموعد لا تتجاوز نهاية الجدول
        if ($end_time > $schedule->end_time) {
            return response()->json(['message' => 'Appointment exceeds available schedule.'], 400);
        }

        // تحقق من وجود موعد مطابق مسبقاً
        $existingAppointment = Appointment::where('manager_id', $manager->id)
            ->where('date', $date)
            ->where('start_time', $start_time)
            ->where('end_time', $end_time)
            ->where('duration', $duration)
            ->first();

        if ($existingAppointment) {
            $alreadyInvited = Invitation::where('related_to_type', get_class($existingAppointment))
                ->where('related_to_id', $existingAppointment->id)
                ->where('invited_user_id', $user->id)
                ->exists();

            if ($alreadyInvited) {
                return response()->json(['message' => 'User already invited to an identical appointment'], 409);
            }

            $appointment = $existingAppointment;
        } else {
            // إنشاء الموعد الجديد
            $appointment = Appointment::create([
                'manager_id' => $manager->id,
                'assistant_id' => null,
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'duration' => $duration,
                'status' => 'pending',
            ]);
        }
    }

    // إنشاء الدعوة
    $invitation = Invitation::create([
        'related_to_type' => get_class($appointment),
        'related_to_id' => $appointment->id,
        'invited_user_id' => $user->id,
        'invited_by_type' => get_class($manager),
        'invited_by_id' => $manager->id,
        'status' => 'pending',
        'sent_at' => now(),
    ]);

    // إرسال إشعار (اختياري)
    $user->notify(new AppointmentInvitation($appointment, $manager));

    return response()->json([
        'message' => 'Invitation sent successfully.',
        'appointment' => $appointment,
        'invitation' => $invitation,
    ]);
}


    public function getSentInvitations(Request $request)
    {
        $manager = Auth::guard('manager')->user();

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        // جلب الدعوات يلي أرسلها المدير
        $invitations = Invitation::where('invited_by_type', get_class($manager))
            ->where('invited_by_id', $manager->id)
            ->with(['invitedUser:id,first_name,last_name,email', 'relatedTo']) // جلب معلومات إضافية
            ->latest()
            ->get();

        return response()->json([
            'invitations' => $invitations
        ]);
    }


    public function getNotes($appointmentId)
    {
        $manager = Auth::guard('manager')->user();
        $appointment = Appointment::findOrFail($appointmentId);

        // تحقق أن المدير صاحب الموعد
        if ($appointment->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notes = AppointmentNote::where('appointment_id', $appointmentId)
            ->with('author') // إذا بدك تعرض اسم المدير أو المساعد
            ->latest()
            ->get();

        return response()->json([
            'appointment_id' => $appointmentId,
            'notes' => $notes
        ]);
    }
}
