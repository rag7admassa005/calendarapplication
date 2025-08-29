<?php

namespace App\Http\Controllers;

use App\Mail\AppointmentApprovedMail;
use App\Mail\AppointmentApprovedNotification as MailAppointmentApprovedNotification;
use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentInvitation as MailAppointmentInvitation;
use App\Mail\AppointmentRejectedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Models\AppointmentRequest;

use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Assistant;
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
use Illuminate\Support\Facades\Mail;
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
        $linkedManager = $assistant->manager;
        if (!$linkedManager) {
            return response()->json(['message' => 'No manager linked to this assistant'], 400);
        }
        $manager = $linkedManager;

        $permission = Permission::where('name', 'view_appointment_requests')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    $validator = Validator::make($request->all(), [
        'status' => 'nullable|in:pending,approved,rejected,rescheduled,cancelled',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $query = AppointmentRequest::with([
        'user:id,first_name,last_name,email',
        'reviewedBy',
        'participants.user:id,first_name,last_name,email' // جلب بيانات المشاركين
    ])->where('manager_id', $manager->id);

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    $appointmentRequests = $query->get()->map(function ($appointment) {
        return [
            'id' => $appointment->id,
            'preferred_date' => $appointment->preferred_date,
            'preferred_start_time' => $appointment->preferred_start_time,
            'preferred_end_time' => $appointment->preferred_end_time,
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
            'participants' => $appointment->participants->map(function ($p) {
                return [
                    'id' => $p->id,
                    'status' => $p->status,
                    'user' => $p->user ? [
                        'id' => $p->user->id,
                        'first_name' => $p->user->first_name,
                        'last_name' => $p->user->last_name,
                        'email' => $p->user->email,
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

public function showAppointments(Request $request)
    {
        $manager = Auth::guard('manager')->user();
        $assistant = Auth::guard('assistant')->user();

        if (!$manager && !$assistant) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // إذا كان المستخدم مساعد
        if ($assistant) {
            $linkedManager = $assistant->manager;
            if (!$linkedManager) {
                return response()->json(['message' => 'No manager linked to this assistant'], 400);
            }
            $manager = $linkedManager;

            $permission = Permission::where('name', 'view_appointment_requests')->first();
            if (!$permission || !$assistant->permissions->contains($permission->id)) {
                return response()->json(['message' => 'Permission denied'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,approved,rejected,rescheduled,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Appointment::with([
            'users:id,first_name,last_name,email',
            'reviewedBy'
        ])->where('manager_id', $manager->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->get()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'preferred_date' => $appointment->date,
                'preferred_start_time' => $appointment->start_time,
                'preferred_end_time' => $appointment->end_time,
                'duration' => $appointment->duration,
                'status' => $appointment->status,

                // صاحب الطلب (أول مستخدم)
                'user' => $appointment->users->first() ? [
                    'id' => $appointment->users->first()->id,
                    'first_name' => $appointment->users->first()->first_name,
                    'last_name' => $appointment->users->first()->last_name,
                    'email' => $appointment->users->first()->email,
                ] : null,

                // المراجِع (مدير أو مساعد)
                'reviewed_by' => $appointment->reviewedBy ? [
                    'type' => class_basename($appointment->reviewed_by_type),
                    'id' => $appointment->reviewedBy->id,
                    'name' => $appointment->reviewedBy->name ?? null,
                ] : null,

                // كل المشاركين بالموعد (من جدول appointment_user)
                'participants' => $appointment->users->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'first_name' => $u->first_name,
                        'last_name' => $u->last_name,
                        'email' => $u->email,
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Appointments retrieved successfully.',
            'data' => $appointments
        ], 200);
    }


//  public function showAppointments(Request $request)
// {
//     $manager = Auth::guard('manager')->user();
//     $assistant = Auth::guard('assistant')->user();

//     if (!$manager && !$assistant) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     if ($assistant) {
//         $linkedManager = $assistant->manager;

//         if (!$linkedManager) {
//             return response()->json(['message' => 'No manager linked to this assistant'], 400);
//         }

//         $manager = $linkedManager;

//         $permission = Permission::where('name', 'view_appointment_requests')->first();
//         if (!$permission || !$assistant->permissions->contains($permission->id)) {
//             return response()->json(['message' => 'Permission denied'], 403);
//         }
//     }

//     $validator = Validator::make($request->all(), [
//         'status' => 'nullable|in:pending,approved,rejected,rescheduled,cancelled',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     $query = Appointment::with([
//         'users:id,first_name,last_name,email',
//         'reviewedBy',
//         'invitations.invitedUser:id,first_name,last_name,email'
//     ])->where('manager_id', $manager->id);

//     if ($request->has('status')) {
//         $query->where('status', $request->status);
//     }

//     $appointments = $query->get()->map(function ($appointment) {
//         return [
//             'id' => $appointment->id,
//             'preferred_date' => $appointment->date,
//             'preferred_start_time' => $appointment->start_time,
//             'preferred_end_time' => $appointment->end_time,
//             'duration' => $appointment->duration,
//             'status' => $appointment->status,

//             // صاحب الطلب (أول مستخدم مرتبط بالموعد)
//             'user' => $appointment->users->first() ? [
//                 'id' => $appointment->users->first()->id,
//                 'first_name' => $appointment->users->first()->first_name,
//                 'last_name' => $appointment->users->first()->last_name,
//                 'email' => $appointment->users->first()->email,
//             ] : null,

//             // الدعوات
//             'invitations' => $appointment->invitations->map(function ($inv) {
//                 return [
//                     'id' => $inv->id,
//                     'status' => $inv->status,
//                     'sent_at' => $inv->sent_at,
//                     'responded_at' => $inv->responded_at,
//                     'invited_user' => $inv->invitedUser ? [
//                         'id' => $inv->invitedUser->id,
//                         'first_name' => $inv->invitedUser->first_name,
//                         'last_name' => $inv->invitedUser->last_name,
//                         'email' => $inv->invitedUser->email,
//                     ] : null,
//                 ];
//             }),
//         ];
//     });

//     return response()->json([
//         'message' => 'Appointments retrieved successfully.',
//         'data' => $appointments
//     ], 200);
// }



 public function approveAppointmentRequest($id)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $request = AppointmentRequest::with('participants.user', 'user')->findOrFail($id);

    $ownerManager = Manager::find($request->manager_id);
    if (!$ownerManager) {
        return response()->json(['message' => 'Manager not found'], 404);
    }

    if ($assistant) {
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }
        $permission = Permission::where('name', 'accept_appointment')->first();
        if (!$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

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

    // المشاركين: صاحب الطلب + المشاركين الآخرين
   // جلب المشاركين المقبولين فقط
$acceptedParticipants = $request->participants()
    ->where('status', 'accepted')
    ->pluck('user_id')
    ->toArray();

// المشاركين = صاحب الطلب + يلي قبلو
$participantsIds = array_merge([$request->user_id], $acceptedParticipants);

$appointment->users()->syncWithoutDetaching($participantsIds);

    // تحديث حالة الطلب وربط المراجع
    $reviewedBy = $manager ?: $assistant;
    $request->update([
        'status' => 'approved',
        'reviewed_by_type' => get_class($reviewedBy),
        'reviewed_by_id' => $reviewedBy->id,
    ]);

    // إشعارات صاحب الطلب
// Mail::to($request->user->email)->send(new MailAppointmentApprovedNotification($appointment));

// إشعارات باقي المشاركين
foreach ($request->participants as $participant) {
    if ($participant->user_id !== $request->user_id) {
        Mail::to($participant->user->email)->send(new AppointmentApprovedMail($appointment));
    }

    if ($assistant) {
        AssistantActivity::create([
            'assistant_id'    => $assistant->id,
            'permission_id'   => $permission->id,
            'related_to_type' => AppointmentRequest::class,
            'related_to_id'   => $request->id,
            'executed_at'     => now(),
        ]);
    }
}

    // إعادة الاستجابة مع حالة كل مستخدم
    $allParticipants = collect([$request->user])->merge(
        $request->participants->map(function ($p) { 
            return $p->user; 
        })
    );

    $participantsWithStatus = $request->participants->map(function ($p) {
        return [
            'id' => $p->user->id,
            'first_name' => $p->user->first_name,
            'last_name' => $p->user->last_name,
            'email' => $p->user->email,
            'status' => $p->status, // pending, accepted, rejected
        ];
    });

    // إضافة صاحب الطلب مع الحالة 'accepted' تلقائيًا
    $participantsWithStatus->prepend([
        'id' => $request->user->id,
        'first_name' => $request->user->first_name,
        'last_name' => $request->user->last_name,
        'email' => $request->user->email,
        'status' => 'accepted',
    ]);

    return response()->json([
        'message' => 'Appointment approved and scheduled.',
        'appointment' => $appointment,
        'appointment_request' => [
            'id' => $request->id,
            'status' => $request->status,
            'created_by' => [
                'id' => $request->user->id,
                'first_name' => $request->user->first_name,
                'last_name' => $request->user->last_name,
                'email' => $request->user->email,
            ],
            'participants' => $participantsWithStatus,
        ]
    ]);

}

public function cancelAppointmentRequest($id)
{
    $manager = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $requestApp = AppointmentRequest::with('participants.user', 'user')->findOrFail($id);

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

    if ($requestApp->status === 'rejected') {
        return response()->json(['message' => 'This appointment request has already been rejected.'], 400);
    }

    // تحديث حالة الطلب
    $reviewedBy = $manager ?: $assistant;
    $requestApp->update([
        'status' => 'rejected',
        'reviewed_by_type' => get_class($reviewedBy),
        'reviewed_by_id' => $reviewedBy->id,
    ]);

    // إشعار صاحب الطلب
   Mail::to($requestApp->user->email)->send(new AppointmentRejectedMail($requestApp, $reviewedBy));

    // إشعار باقي المشاركين
    foreach ($requestApp->participants as $participant) {
    if ($participant->user_id !== $requestApp->user_id) {
        Mail::to($participant->user->email)
            ->send(new AppointmentRejectedMail($requestApp, $reviewedBy));
    }
    }

    // حذف الموعد إذا كان موجود
    $existingAppointment = Appointment::where('date', $requestApp->preferred_date)
        ->where('start_time', $requestApp->preferred_start_time)
        ->whereHas('users', function ($query) use ($requestApp) {
            $query->where('users.id', $requestApp->user_id);
        })->first();

    if ($existingAppointment) {
        $existingAppointment->users()->detach();
        $existingAppointment->delete();
    }

    // تسجيل النشاط للمساعد
    if ($assistant) {
        AssistantActivity::create([
            'assistant_id' => $assistant->id,
            'permission_id' => $permission->id,
            'appointment_request_id' => $requestApp->id,
            'executed_at' => now(),
        ]);
    }

    // تجهيز المشاركين للاستجابة
    $participantsWithStatus = $requestApp->participants->map(function ($p) {
        return [
            'id' => $p->user->id,
            'first_name' => $p->user->first_name,
            'last_name' => $p->user->last_name,
            'email' => $p->user->email,
            'status' => $p->status,
        ];
    });

    // إضافة صاحب الطلب بحالة accepted
    $participantsWithStatus->prepend([
        'id' => $requestApp->user->id,
        'first_name' => $requestApp->user->first_name,
        'last_name' => $requestApp->user->last_name,
        'email' => $requestApp->user->email,
        'status' => 'accepted',
    ]);

    return response()->json([
        'message' => 'Appointment request rejected successfully',
        'appointment_request' => [
            'id' => $requestApp->id,
            'status' => $requestApp->status,
            'created_by' => [
                'id' => $requestApp->user->id,
                'first_name' => $requestApp->user->first_name,
                'last_name' => $requestApp->user->last_name,
                'email' => $requestApp->user->email,
            ],
            'participants' => $participantsWithStatus,
        ]
    ], 200);
}




public function rescheduleAppointmentRequest(Request $http, $id)
{
    $manager   = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // احمل الطلب مع العلاقات
    $appointmentRequest = AppointmentRequest::with('participants.user', 'user')->findOrFail($id);

    $ownerManager = Manager::find($appointmentRequest->manager_id);
    if (!$ownerManager) {
        return response()->json(['message' => 'Manager not found'], 404);
    }

    // صلاحيات المساعد
    if ($assistant) {
        if ($assistant->manager_id !== $ownerManager->id) {
            return response()->json(['message' => 'Unauthorized assistant'], 403);
        }

        $permission = Permission::where('name', 'reschedule_appointment')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    }

    if ($appointmentRequest->status === 'rescheduled') {
        return response()->json(['message' => 'This appointment request has already been rescheduled.'], 400);
    }

    // فاليديشن على مدخلات الـHTTP
    $validator = Validator::make($http->all(), [
        'date'       => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $date          = $http->input('date');
    $userStartTime = Carbon::createFromFormat('H:i', $http->input('start_time'));

    // جدولة اليوم المحدد
    $schedules = Schedule::where('manager_id', $ownerManager->id)
        ->where('date', $date)
        ->where('is_available', true)
        ->get();

    if ($schedules->isEmpty()) {
        return response()->json(['message' => 'This day is not available for scheduling.'], 422);
    }

    // ابحث عن السلوت المطابق
    $matched = null;

    foreach ($schedules as $schedule) {
        $start    = Carbon::parse($schedule->start_time);
        $end      = Carbon::parse($schedule->end_time);
        $duration = $schedule->meeting_duration_1 ?? $schedule->duration ?? 30;

        $slot = $start->copy();
        while ($slot->lte($end->copy()->subMinutes($duration))) {
            if ($slot->format('H:i') === $userStartTime->format('H:i')) {
                $matched = [
                    'start'    => $slot->copy(),
                    'end'      => $slot->copy()->addMinutes($duration),
                    'duration' => $duration,
                ];
                break 2;
            }
            $slot->addMinutes($duration);
        }
    }

    if (!$matched) {
        return response()->json([
            'message' => 'The selected time does not match the manager\'s available slots.',
        ], 422);
    }

    // تحقق التداخل
    $userStart = $matched['start'];
    $userEnd   = $matched['end'];

    $overlapping = Appointment::where('manager_id', $ownerManager->id)
        ->where('date', $date)
        ->where(function ($query) use ($userStart, $userEnd) {
            $query->whereBetween('start_time', [$userStart->format('H:i'), $userEnd->format('H:i')])
                  ->orWhereBetween('end_time',   [$userStart->format('H:i'), $userEnd->format('H:i')])
                  ->orWhere(function ($q) use ($userStart, $userEnd) {
                      $q->where('start_time', '<=', $userStart->format('H:i'))
                        ->where('end_time',   '>=', $userEnd->format('H:i'));
                  });
        })
        ->exists();

    if ($overlapping) {
        return response()->json(['message' => 'The selected time overlaps with an existing appointment.'], 409);
    }

    // أنشئ الموعد الجديد
    $appointment = Appointment::create([
        'date'       => $date,
        'start_time' => $userStart->format('H:i'),
        'end_time'   => $userEnd->format('H:i'),
        'duration'   => $matched['duration'],
        'status'     => 'rescheduled',
        'manager_id' => $ownerManager->id,
    ]);

    // اربط صاحب الطلب + المشاركين المقبولين فقط
    $acceptedParticipants = $appointmentRequest->participants
        ->where('status', 'accepted')
        ->values(); // reindex

    $userIds = $acceptedParticipants->pluck('user_id')->toArray();
    $userIds[] = $appointmentRequest->user_id; // صاحب الطلب دائمًا

    $appointment->users()->syncWithoutDetaching(array_unique($userIds));

    // حدث حالة الطلب
    $reviewedBy = $manager ?: $assistant;
    $appointmentRequest->update([
        'status'          => 'rescheduled',
        'reviewed_by_type'=> get_class($reviewedBy),
        'reviewed_by_id'  => $reviewedBy->id,
    ]);

    // إشعارات
    // الأفضل استخدام Notification مخصصة لإعادة الجدولة للطرفين
  Mail::to($appointmentRequest->user->email)
    ->send(new AppointmentRescheduledMail($appointment, $reviewedBy));

// إرسال لكل المشاركين
foreach ($acceptedParticipants as $p) {
    if ($p->user_id !== $appointmentRequest->user_id) {
        Mail::to($p->user->email)
            ->send(new AppointmentRescheduledMail($appointment, $reviewedBy));
    }
}

    // سجل نشاط المساعد
    if ($assistant) {
        AssistantActivity::create([
            'assistant_id'    => $assistant->id,
            'permission_id'   => $permission->id, // آمن لأننا تحقّقنا منه فوق
            'related_to_type' => AppointmentRequest::class,
            'related_to_id'   => $appointmentRequest->id,
            'executed_at'     => now(),
        ]);
    }

// رجّع الكل مع الحالة (مو بس المقبولين)
$participantsForResponse = collect([
    [
        'id'         => $appointmentRequest->user->id,
        'first_name' => $appointmentRequest->user->first_name,
        'last_name'  => $appointmentRequest->user->last_name,
        'email'      => $appointmentRequest->user->email,
        'status'     => 'accepted', // صاحب الطلب دايمًا accepted
    ]
])->merge(
    $appointmentRequest->participants->map(function ($p) {
        return [
            'id'         => $p->user->id,
            'first_name' => $p->user->first_name,
            'last_name'  => $p->user->last_name,
            'email'      => $p->user->email,
            'status'     => $p->status, // ممكن تكون pending / accepted / rejected
        ];
    })
);

    return response()->json([
        'message' => 'Appointment rescheduled successfully',
        'appointment' => $appointment,
        'appointment_request' => [
            'id'     => $appointmentRequest->id,
            'status' => $appointmentRequest->status,
            'created_by' => [
                'id'         => $appointmentRequest->user->id,
                'first_name' => $appointmentRequest->user->first_name,
                'last_name'  => $appointmentRequest->user->last_name,
                'email'      => $appointmentRequest->user->email,
            ],
            'participants' => $participantsForResponse,
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
public function inviteUsers(Request $request)
{
    $manager   = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response(['message' => 'Unauthorized'], 401);
    }

    // تحديد المدير المالك
    if ($assistant) {
        if (!$assistant->manager) {
            return response()->json(['message' => 'This assistant does not belong to any manager'], 403);
        }

        $ownerManager = $assistant->manager;

        // تحقق من صلاحية المساعد
        $permission = Permission::where('name', 'invite_users')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    } else {
        $ownerManager = $manager;
    }

    // تحقق من المدخلات
    $validator = Validator::make($request->all(), [
        'title'           => 'required|string|max:255' ,
        'description'     => 'required|string|max:255',
        'user_ids'   => 'required|array|min:1',
        'user_ids.*' => 'exists:users,id',
        'date'       => 'required|date',
        'start_time' => 'required|date_format:H:i',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $date       = $request->date;
    $start_time = $request->start_time;
    $day        = strtolower(Carbon::parse($date)->format('l'));

    // تحقق أن الوقت موجود ضمن الـ schedule تبع المدير
    $schedule = Schedule::where('manager_id', $ownerManager->id)
        ->where('day_of_week', $day)
        ->where('is_available', true)
        ->where('start_time', '<=', $start_time)
        ->where('end_time', '>', $start_time)
        ->first();

    if (!$schedule) {
        return response()->json(['message' => 'The manager is not available at this time.'], 400);
    }

    // احسب وقت النهاية
    $duration     = $schedule->meeting_duration_1;
    $end_time_obj = Carbon::createFromFormat('H:i', $start_time)->addMinutes($duration);
    $end_time     = $end_time_obj->format('H:i');

    if ($end_time > $schedule->end_time) {
        return response()->json(['message' => 'Appointment exceeds available schedule.'], 400);
    }

    // تحقق إذا في موعد مثبت بنفس الوقت
    $conflictAppointment = Appointment::where('manager_id', $ownerManager->id)
        ->where('date', $date)
        ->where('start_time', $start_time)
        ->where('end_time', $end_time)
        ->exists();

    if ($conflictAppointment) {
        return response()->json(['message' => 'This time slot is already taken'], 409);
    }

    // لو ما في تعارض: أرسل الدعوات
    $inviter = $manager ?: $assistant;
    $results = [];

    foreach ($request->user_ids as $userId) {
        $user = User::find($userId);

        // تحقق أن المستخدم تابع لنفس المدير
        $belongsToManager = $ownerManager->users()->where('users.id', $user->id)->exists();
        if (!$belongsToManager) {
            $results[] = ['user_id' => $user->id, 'status' => 'failed', 'reason' => 'User not belongs to this manager'];
            continue;
        }

        // تحقق إذا مدعو مسبقًا بنفس التاريخ والوقت
        $alreadyInvited = Invitation::where('invited_user_id', $user->id)
            ->where('date', $date)
            ->where('time', $start_time)
            ->exists();

        if ($alreadyInvited) {
            $results[] = ['user_id' => $user->id, 'status' => 'failed', 'reason' => 'Already invited to this slot'];
            continue;
        }

        // إنشاء الدعوة
        $invitation = Invitation::create([
            'invited_user_id' => $user->id,
            'invited_by_type' => get_class($inviter),
            'invited_by_id'   => $inviter->id,
            'title'           => $request->title ?? null,
            'description'     => $request->description ?? null,
            'date'            => $date,
            'time'            => $start_time,
            'duration'        => $duration,
            'status'          => 'pending',
            'sent_at'         => now(),
        ]);

        // إرسال إشعار
       // $user->notify(new AppointmentInvitation($invitation, $ownerManager));
        Mail::to($user->email)->send(new MailAppointmentInvitation($invitation, $ownerManager));
        // تسجيل نشاط لو المساعد هو يلي بعت الدعوة
        if ($assistant) {
            AssistantActivity::create([
                'assistant_id'    => $assistant->id,
                'permission_id'   => $permission->id,
                'related_to_type' => Invitation::class,
                'related_to_id'   => $invitation->id,
                'executed_at'     => now(),
            ]);
        }

        $results[] = ['user_id' => $user->id, 'status' => 'success', 'invitation' => $invitation];
    }

    return response()->json([
        'message' => 'Invitations processed.',
        'results' => $results,
    ]);
}


public function getSentInvitations(Request $request)
{
    $manager   = Auth::guard('manager')->user();
    $assistant = Auth::guard('assistant')->user();

    if (!$manager && !$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    if ($assistant) {
        if (!$assistant->manager) {
            return response()->json(['message' => 'This assistant does not belong to any manager'], 403);
        }

        $ownerManager = $assistant->manager;

        $permission = Permission::where('name', 'view_invitations')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }
    } else {
        $ownerManager = $manager;
    }

    $invitations = Invitation::with(['invitedUser', 'inviter'])
        ->where(function ($q) use ($ownerManager) {
            $q->where('invited_by_type', Manager::class)
              ->where('invited_by_id', $ownerManager->id);
        })
        ->orWhere(function ($q) use ($ownerManager) {
            $assistantIds = $ownerManager->assistants->pluck('id')->toArray();
            $q->where('invited_by_type', Assistant::class)
              ->whereIn('invited_by_id', $assistantIds);
        })
        ->orderBy('date', 'desc')
        ->orderBy('time', 'desc')
        ->get();

    // نجمع الدعوات إذا نفس (المرسل + الوقت + التاريخ)
    $grouped = $invitations->groupBy(function ($invitation) {
        return $invitation->date.'|'.$invitation->time.'|'.$invitation->invited_by_type.'|'.$invitation->invited_by_id;
    });

    $results = $grouped->map(function ($group) {
        $first = $group->first();

        return [
            'invitation_id' => $first->id, // ممكن تختار أول id كممثل
            'title'         => $first->title,
            'description'   => $first->description,
            'date'          => $first->date,
            'time'          => $first->time,
            'duration'      => $first->duration,
            'status'        => $first->status,
            'sent_at'       => $first->sent_at,
            'invited_users' => $group->map(function ($invitation) {
                return [
                    'id'    => $invitation->invitedUser?->id,
                    'name'  => $invitation->invitedUser?->name,
                    'email' => $invitation->invitedUser?->email,
                ];
            })->filter()->values(),
            'invited_by' => [
                'type' => class_basename($first->invited_by_type),
                'id'   => $first->invited_by_id,
                'name' => $first->inviter?->name ?? null,
            ]
        ];
    })->values();

    if ($assistant) {
        AssistantActivity::create([
            'assistant_id'    => $assistant->id,
            'permission_id'   => $permission->id,
            'related_to_type' => Invitation::class,
            'related_to_id'   => null,
            'executed_at'     => now(),
        ]);
    }

    return response()->json([
        'message'     => 'Invitations retrieved successfully',
        'invitations' => $results,
    ]);
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
