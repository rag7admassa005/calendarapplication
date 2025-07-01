<?php

namespace App\Http\Controllers;

use App\Models\AppointmentRequest;
use Illuminate\Http\Request;
   use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

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

        $duration = $schedule->meeting_duration_1 ?? 30; // افتراضي 30
        $slots = [];

        while ($start->addMinutes(0)->lt($end)) {
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
            'day_of_week' => $schedule->day_of_week,
            'slots'       => $slots,
        ];
    }

    return response()->json([
        'manager_id' => $user->manager_id,
        'schedule'   => $result,
    ]);
}

   /* public function requestAppointment(Request $request)
{
    $user = Auth::guard('api')->user();

    $validator = Validator::make($request->all(), [
        'preferred_date'        => 'required|date|after:today',
        'preferred_start_time'  => 'required|date_format:H:i',
        'preferred_duration'    => 'required|in:30,60',
        'reason'                => 'required|string|max:500',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $manager = $user->manager;

    if (!$manager) {
        return response()->json(['message' => 'Manager not found.'], 404);
    }

    // حساب وقت نهاية الموعد بناءً على المدة
    $startTime = Carbon::createFromFormat('H:i', $request->preferred_start_time);
    $endTime = $startTime->copy()->addMinutes($request->preferred_duration);

    // ✅ تحقق من تداخل المواعيد مع مواعيد أخرى
    $conflict = AppointmentRequest::where('manager_id', $manager->id)
        ->where('preferred_date', $request->preferred_date)
        ->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('preferred_start_time', [$startTime->format('H:i'), $endTime->subMinute()->format('H:i')])
              ->orWhereBetween('preferred_end_time', [$startTime->format('H:i'), $endTime->format('H:i')])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('preferred_start_time', '<', $startTime->format('H:i'))
                     ->where('preferred_end_time', '>', $endTime->format('H:i'));
              });
        })
        ->exists();

    if ($conflict) {
        return response()->json(['message' => 'This time is already booked.'], 409);
    }

    // إنشاء الموعد
    $appointment = AppointmentRequest::create([
        'user_id'              => $user->id,
        'manager_id'           => $manager->id,
        'preferred_date'       => $request->preferred_date,
        'preferred_start_time' => $startTime->format('H:i'),
        'preferred_end_time'   => $endTime->format('H:i'),
        'preferred_duration'   => $request->preferred_duration,
        'reason'               => $request->reason,
        'status'               => 'pending',
        'requested_at'         => now(),
    ]);

    return response()->json([
        'message' => 'Appointment request submitted successfully.',
        'appointment' => $appointment
    ], 201);
}*/
}
