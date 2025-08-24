<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Mail;
//use Illuminate\Support\Facades\Log;
use App\Models\AppointmentRequest;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;

use App\Mail\AppointmentCancelledMail;
use App\Mail\InvitationResponsedMail;
use App\Models\Appointment;
use App\Models\AppointmentRequestParticipant;
use App\Models\Invitation;
use App\Models\Manager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule as FacadesSchedule;

class UserController extends Controller
{ 
     public function viewManagerSchedule(Request $request)
    {
        $user = Auth::guard('api')->user();

        if (!$user || !$user->manager_id) {
            return response()->json(['message' => 'Manager not assigned to this user'], 400);
        }

        $schedules = Schedule::where('manager_id', $user->manager_id)
                             ->where('is_available', 1)
                             ->get();

        $result = [];

        foreach ($schedules as $schedule) {
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);
          $duration = in_array($schedule->meeting_duration_1, [30, 60]) ? $schedule->meeting_duration_1 : 30;


            $slots = [];

            while ($start->copy()->lt($end)) {
                $slotEnd = $start->copy()->addMinutes($duration);

                if ($slotEnd->gt($end)) {
                    break;
                }

                $slots[] = [
                    'from' => $start->format('H:i'),
                    'to'   => $slotEnd->format('H:i'),
                ];

                $start = $slotEnd;
            }

            $result[] = [
                'schedule_id'  => $schedule->id, // مشان الفرونت ليميز الفترات
                'day_of_week' => $schedule->day_of_week,
                'slots'       => $slots,
            ];
        }

        return response()->json([
            'manager_id' => $user->manager_id,
            'schedule'   => $result,
        ]);
    }

public function requestAppointment(Request $request)
{
    $user = Auth::guard('api')->user();

    $validator = Validator::make($request->all(), [
        'preferred_date'       => 'required|date|after:today',
        'preferred_start_time' => 'required|date_format:H:i',
        'reason'               => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $manager_id = $user->manager_id;
    $date       = $request->preferred_date;
    $start_time = $request->preferred_start_time;
    $day        = strtolower(Carbon::parse($date)->format('l'));

    // جلب جدول المواعيد للمدير
    $schedule = Schedule::where('manager_id', $manager_id)
        ->where('day_of_week', $day)
        ->where('is_available', true)
        ->where('start_time', '<=', $start_time)
        ->where('end_time', '>', $start_time)
        ->first();

    if (!$schedule) {
        return response()->json(['message' => 'The manager is not available at this time.'], 400);
    }

    $duration = $schedule->meeting_duration_1 ;
    $end_time = Carbon::createFromFormat('H:i', $start_time)->addMinutes($duration)->format('H:i');

    // التحقق من تعارض مع مواعيد مقبولة فقط
    $conflict = AppointmentRequest::where('manager_id', $manager_id)
        ->where('preferred_date', $date)
        ->where('status', 'accepted')
        ->where(function ($query) use ($start_time, $end_time) {
            $query->whereBetween('preferred_start_time', [$start_time, $end_time])
                ->orWhereBetween('preferred_end_time', [$start_time, $end_time])
                ->orWhere(function ($q) use ($start_time, $end_time) {
                    $q->where('preferred_start_time', '<', $start_time)
                      ->where('preferred_end_time', '>', $end_time);
                });
        })
        ->exists();

    if ($conflict) {
        return response()->json(['message' => 'This time slot has already been booked.'], 409);
    }

    // تخزين الطلب في جدول appointment_requests
    $appointment = AppointmentRequest::create([
        'user_id'               => $user->id,
        'manager_id'           => $manager_id,
        'preferred_date'       => $date,
        'preferred_start_time' => $start_time,
        'preferred_end_time'   => $end_time,
        'preferred_duration'   => $duration,
        'reason'               => $request->reason,
        'status'               => 'pending',
        'requested_at'         => now(),
    ]);

    // إشعار المدير على الإيميل
    $manager = \App\Models\Manager::find($manager_id);
    if ($manager && $manager->email) {
        Mail::to($manager->email)->send(
            new \App\Mail\AppointmentRequestedMail($user, $appointment)
        );
    }

    return response()->json([
        'message'     => 'Appointment request submitted successfully. Status is pending.',
        'appointment' => [ 
         'id'=>$appointment->id,
        'user_id'=> $user->id,
        'manager_id'           => $manager_id,
        'preferred_date'       => $date,
        'preferred_start_time' => $start_time,
        'end_time'   => $end_time,
        'duration'   => $duration,
        'reason'               => $request->reason,
        'status'               => 'pending',
        'requested_at'         => now(),

        ],
    ], 201);
}

    public function rescheduleAppointment(Request $request, $id)
{
    $user = Auth::guard('api')->user();

    if(!$user)
    {
        return response()->json(['message' => 'user is not found '], 404);
    }
    $appointment = AppointmentRequest::where('id', $id)
        ->where('user_id', $user->id)
       ->where('status', ['pending'])
        ->first();

    if (!$appointment) {
        return response()->json(['message' => 'Appointment not found or cannot be rescheduled.'], 404);
    }

    // تحقق من أن الموعد لم يبدأ بعد
    $appointmentDateTime = Carbon::parse("{$appointment->preferred_date} {$appointment->preferred_start_time}");
    if (now()->gt($appointmentDateTime)) {
        return response()->json(['message' => 'You cannot reschedule an appointment that has already started or passed.'], 403);
    }

    // تحقق من الزمن المتبقي (يسمح بإعادة الجدولة قبل 12 ساعة من الموعد)
    if ($appointment->status === 'accepted' && now()->diffInHours($appointmentDateTime, false) < 12) {
        return response()->json(['message' => 'You can only reschedule up to 12 hours before the appointment.'], 403);
    }
    $validator = Validator::make($request->all(), [
        'preferred_date' => 'required|date|after:today',
        'preferred_start_time' => 'required|date_format:H:i',
        'reason' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
        // الحصول على مدة الموعد من جدول الجدول الزمني
    $day = strtolower(Carbon::parse($request->preferred_date)->format('l'));
    $schedule = Schedule::where('manager_id', $user->manager_id)
        ->where('day_of_week', $day)
        ->where('is_available', true)
        ->where('start_time', '<=', $request->preferred_start_time)
        ->first();

    if (!$schedule) {
        return response()->json(['message' => 'The manager is not available on this day/time.'], 400);
    }

    $duration = $schedule->meeting_duration_1 ?? 30;
    $end_time = Carbon::createFromFormat('H:i', $request->preferred_start_time)
        ->addMinutes($duration)
        ->format('H:i');


    // التحقق من التعارض
    $conflict = AppointmentRequest::where('manager_id', $user->manager_id)
        ->where('preferred_date', $request->preferred_date)
        ->where('id', '!=', $id)
        ->whereIn('status', ['pending', 'accepted'])
        ->where(function ($query) use ($request, $end_time) {
            $query->whereBetween('preferred_start_time', [$request->preferred_start_time, $end_time])
                ->orWhereBetween('preferred_end_time', [$request->preferred_start_time, $end_time])
                ->orWhere(function ($query) use ($request, $end_time) {
                    $query->where('preferred_start_time', '<', $request->preferred_start_time)
                        ->where('preferred_end_time', '>', $end_time);
                });
        })
        ->exists();

    if ($conflict) {
        return response()->json(['message' => 'The selected time is already booked or pending.'], 409);
    }

    $appointment->update([
        'preferred_date' => $request->preferred_date,
        'preferred_start_time' => $request->preferred_start_time,
        'preferred_end_time' => $end_time,
        'preferred_duration' => $duration,
        'reason' => $request->reason,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $manager = $appointment->manager;
    if ($manager && $manager->email) {
        Mail::to($manager->email)->send(new \App\Mail\AppointmentRequestedMail($user, $appointment));
    }

    return response()->json(['message' => 'Appointment rescheduled successfully and is now pending.',$appointment], 200);
}

public function cancelAppointment(Request $request, $id)
{
    $user = Auth::guard('api')->user();
    if(!$user)
    {
        return response()->json(['message' => 'user is not found '], 404);
    }

    $validator = Validator::make($request->all(), [
        'reason' => 'required|string|max:1000',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $appointment = AppointmentRequest::where('id', $id)
        ->where('user_id', $user->id)
        ->whereNotIn('status', ['cancelled', 'rejected'])
        ->first();

    if (!$appointment) {
        return response()->json(['message' => 'Appointment not found or already cancelled/rejected.'], 404);
    }

    $appointmentTime = Carbon::parse("{$appointment->preferred_date} {$appointment->preferred_start_time}");
    if (now()->gt($appointmentTime)) {
        return response()->json(['message' => 'Cannot cancel an appointment that has already started.'], 403);
    }

    // شرط الزمن: لا يُسمح بالإلغاء قبل أقل من 4 ساعات من وقت الموعد
    if (now()->diffInHours($appointmentTime, false) < 4) {
        return response()->json(['message' => 'You can only cancel at least 4 hours before the appointment.'], 403);
    }

    $appointment->status = 'cancelled';
    $appointment->save();

    $manager = $appointment->manager;
    if ($manager && $manager->email) {
        Mail::to($manager->email)->send(new AppointmentCancelledMail(
            $user,
            $appointment,
            $request->reason
        ));
    }

    return response()->json(['message' => 'Appointment cancelled successfully.'], 200);
}

public function myInvitations()
{
    $user = Auth::guard('api')->user();

    $invitations = Invitation::with('relatedTo') // يجلب الموعد المرتبط إن وُجد
        ->where('invited_user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

    $result = $invitations->map(function ($invitation) {
        return [
            'invitation_id'    => $invitation->id,
            'status'           => $invitation->status,
            'sent_at'          => $invitation->sent_at,
            'responded_at'     => $invitation->responded_at,
            'appointment_info' => $invitation->relatedTo ? [
                'id'           => $invitation->relatedTo->id,
                'date'         => $invitation->relatedTo->date,
                'start_time'   => $invitation->relatedTo->start_time,
                'end_time'     => $invitation->relatedTo->end_time,
                'location'     => $invitation->relatedTo->location ?? null,
                'description'  => $invitation->relatedTo->description ?? null,
            ] : null,
        ];
    });

    return response()->json([
        'invitations' => $result
    ], 200);
}


public function respondToInvitation(Request $request, $id)
{
    $user = Auth::guard('api')->user();

    // التحقق من وجود الدعوة
    $invitation = Invitation::where('id', $id)
        ->where('invited_user_id', $user->id)
        ->where('status', 'pending')
        ->first();

    if (!$invitation) {
        return response()->json([
            'message' => 'Invitation not found or already responded to.'
        ], 404);
    }

    // التحقق من المدخلات
    $validator = Validator::make($request->all(), [
        'response' => 'required|in:accepted,rejected',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    // تحديث حالة الدعوة
    $invitation->update([
        'status' => $request->response,
        'responded_at' => now(),
    ]);

    // إشعار المرسل (اختياري إذا بدك تبقيه)
    if ($invitation->invited_by_type && $invitation->invited_by_id) {
        $sender = $invitation->invited_by_type::find($invitation->invited_by_id);
        if ($sender && $sender->email) {
            Mail::to($sender->email)->send(
                new \App\Mail\InvitationResponsedMail($user, $invitation)
            );
        }
    }

    $responseMessage = $request->response === 'accepted'
        ? 'Invitation accepted successfully.'
        : 'Invitation rejected successfully.';

    return response()->json([
        'message' => $responseMessage,
        'invitation' => $invitation
    ], 200);
}

public function allUsers(Request $request)
{
    $user = Auth::guard('api')->user();

    if (!$user || !$user->manager_id) {
        return response()->json([
            'message' => 'User or manager not found.'
        ], 404);
    }

    // جلب المستخدمين المرتبطين بنفس المدير (عدا المستخدم الحالي)
    $colleagues = User::where('manager_id', $user->manager_id)
        ->where('id', '!=', $user->id);

    // فلترة حسب الـ job_id إذا تم تمريره
    if ($request->filled('job_id')) {
        $colleagues->where('job_id', $request->job_id);
    }

    $users = $colleagues->get();

    return response()->json([
        'users' => $users
    ], 200);
}

public function createAppointmentRequest(Request $request)
{
    $validator = Validator::make($request->all(), [
        'manager_id' => 'required|exists:managers,id',
        'preferred_date' => 'required|date',
        'preferred_start_time' => 'required',
        'reason' => 'nullable|string',
        'participants' => 'array', // ids of users
        'participants.*' => 'exists:users,id',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $user = Auth::guard('api')->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $managerId = $request->manager_id;
    $date = $request->preferred_date;
    $time = $request->preferred_start_time;

    // ✅ تحقق من أن الوقت موجود في جدول مواعيد المدير
   $available = Schedule::where('manager_id', $managerId)
        ->where(function ($q) use ($date) {
            $q->where('date', $date) // إما تاريخ محدد
              ->orWhere('day_of_week', \Carbon\Carbon::parse($date)->dayOfWeek); // أو يوم الأسبوع
        })
        ->where('is_available', true)
        ->where('start_time', '<=', $time)
        ->where('end_time', '>', $time)
        ->first();

    if (!$available) {
        return response()->json(['message' => 'The manager is not available at this time.'], 422);
    }

    // ✅ تحقق أنو ما في موعد بنفس الوقت
    $conflict = Appointment::where('manager_id', $managerId)
        ->where('date', $date)
        ->where('start_time', $time)
        ->exists();

    if ($conflict) {
        return response()->json(['message' => 'This time slot is already booked.'], 422);
    }

    // 1- إنشاء الطلب
    $appointmentRequest = AppointmentRequest::create([
        'user_id' => $user->id,
        'manager_id' => $managerId,
        'preferred_date' => $date,
        'preferred_start_time' => $time,
        'preferred_end_time' => $available->end_time,
        'preferred_duration' => $available->meeting_duration_1,
        'reason' => $request->reason,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    // 2- إضافة المشاركين إذا موجودين
    if ($request->has('participants')) {
        foreach ($request->participants as $participantId) {
            AppointmentRequestParticipant::create([
                'appointment_request_id' => $appointmentRequest->id,
                'user_id' => $participantId,
                'status' => 'pending',
            ]);
        }
    }

    return response()->json([
        'message' => 'Appointment request created successfully',
        'data' => $appointmentRequest->load('participants.user'),
    ], 201);
}


public function respondToRequest(Request $request, $appointmentRequestId)
{
    $validator=Validator::make($request->all(),[
        'status' => 'required|in:accepted,rejected',
    ]);

       if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
   
$user = Auth::guard('api')->user();

if (!$user) {
    return response()->json(['message' => 'Unauthenticated'], 401);
}

    // جلب السجل من جدول المشاركين
    $participant = AppointmentRequestParticipant::where('appointment_request_id', $appointmentRequestId)
        ->where('user_id', $user->id)
        ->first();

    if (!$participant) {
        return response()->json(['message' => 'You are not a participant in this request'], 403);
    }

    // تحديث الحالة
    $participant->update([
        'status' => $request->status,
    ]);

    return response()->json([
        'message' => 'Response submitted successfully',
        'data' => $participant->load('user', 'appointmentRequest'),
    ]);
}


public function getMyIncomingRequests()
{
    $user = Auth::guard('api')->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $participants = AppointmentRequestParticipant::with([
        'appointmentRequest.user:id,first_name,last_name,email', // صاحب الطلب (من جدول users)
        'appointmentRequest.manager:id,name',                   // المدير (من جدول managers)
    ])
    ->where('user_id', $user->id)
    ->get();

    if ($participants->isEmpty()) {
        return response()->json(['message' => 'No appointment requests found']);
    }

    return response()->json([
        'requests' => $participants->map(function ($p) {
            $fromUser = $p->appointmentRequest->user;
            $toManager = $p->appointmentRequest->manager;

            return [
                'appointment_request_id' => $p->appointment_request_id,
                'from_user' => [
                    'id' => $fromUser->id,
                    'full_name' => trim($fromUser->first_name . ' ' . $fromUser->last_name),
                    'email' => $fromUser->email,
                ],
                'to_manager' => [
                    'id' => $toManager->id,
                    'name' => $toManager->name,
                ],
                'status' => $p->status,
                'created_at' => $p->appointmentRequest->created_at,
            ];
        })
    ]);
}






}