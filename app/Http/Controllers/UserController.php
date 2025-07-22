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
use App\Models\Invitation;
use App\Models\Manager;
use Illuminate\Support\Facades\DB;

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

    return response()->json(['message' => 'Appointment rescheduled successfully and is now pending.'], 200);
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

        $invitation = Invitation::where('id', $id)
            ->where('invited_user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invitation not found or already responded to.'], 404);
        }

        // التأكد من أن الدعوة خاصة بموعد
        if ($invitation->related_to_type !== 'App\\Models\\Appointment') {
            return response()->json(['message' => 'Invalid invitation type.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'response' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // الحصول على الموعد المرتبط
        $appointment = Appointment::find($invitation->related_to_id);
        if (!$appointment) {
            return response()->json(['message' => 'Related appointment not found.'], 404);
        }

        // التحقق من أن الموعد لم يبدأ بعد
        $appointmentDateTime = Carbon::parse("{$appointment->start_time}");
        if (now()->gt($appointmentDateTime)) {
            return response()->json(['message' => 'You cannot respond to an invitation for an appointment that has already started or passed.'], 403);
        }

        // تحديث حالة الدعوة
        $invitation->update([
            'status' => $request->response,
            'responded_at' => now(),
        ]);

        // في حالة القبول، إضافة المستخدم للموعد
        if ($request->response === 'accepted') {
            // التحقق من عدم وجود المستخدم مسبقاً
            $existingUser = DB::table('appointment_user')
                ->where('appointment_id', $appointment->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$existingUser) {
                DB::table('appointment_user')->insert([
                    'appointment_id' => $appointment->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // إرسال إشعار للمرسل
        if ($invitation->invited_by_type && $invitation->invited_by_id) {
            $sender = $invitation->invited_by_type::find($invitation->invited_by_id);
            if ($sender && $sender->email) {
                Mail::to($sender->email)->send(new \App\Mail\InvitationResponsedMail($user, $invitation, $appointment));
            }
        }

        $responseMessage = $request->response === 'accepted' 
            ? 'Invitation accepted successfully.' 
            : 'Invitation rejected successfully.';

        return response()->json(['message' => $responseMessage], 200);
    }
    public function AllUsers (Request $request)
{
    $user = Auth::guard('api')->user();

    // جلب المستخدمين المرتبطين بنفس المدير (عدا المستخدم الحالي)
    $colleagues = User::where('manager_id', $user->manager_id)
        ->where('id', '!=', $user->id)
        ->select('id', 'first_name', 'last_name', 'email')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $colleagues
    ]);
}
}