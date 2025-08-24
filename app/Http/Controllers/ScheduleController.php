<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Manager;
use App\Models\Permission;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Nette\Schema\Schema;

class ScheduleController extends Controller
{
public function addSchedule(Request $request)
{
    $validator = Validator::make($request->all(), [
        'day_of_week' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'repeat_for_weeks' => 'required|int|min:1',
        'is_available' => 'nullable|boolean',
        'meeting_duration' => 'required|in:30,60',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $manager = Auth::guard('manager')->user();
    if (!$manager) {
        return response()->json(['message' => 'Manager not authenticated'], 401);
    }

    $isAvailable = $request->is_available ?? true;
    $duration = (int) $request->meeting_duration;
    $dayName = $request->day_of_week;
    $repeatWeeks = (int) $request->repeat_for_weeks;

    // معالجة اليوم غير المتاح
    if (!$isAvailable) {
        $hasSchedules = Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $dayName)
            ->exists();

        if ($hasSchedules) {
            return response()->json(['message' => 'Cannot mark this day as unavailable because schedules already exist'], 422);
        }

      $targetDate = Carbon::now()->next($dayName);
if ($targetDate->isSameDay(Carbon::now())) {
    $targetDate->addWeek();
}

for ($i = 0; $i < $repeatWeeks; $i++) {
    $date = $targetDate->copy()->addWeeks($i);

    Schedule::create([
        'manager_id' => $manager->id,
        'day_of_week' => $dayName,
        'date' => $date->toDateString(),
        'start_time' => '00:00',
        'end_time' => '23:59',
        'is_available' => false,
        'repeat_for_weeks' => 1,
        'meeting_duration_1' => 30,
        'meeting_duration_2' => 60,
    ]);
}

        return response()->json(['message' => 'Day marked as unavailable successfully'], 201);
    }

    // التأكد أن اليوم ليس غير متاح مسبقًا
    $hasUnavailableDay = Schedule::where('manager_id', $manager->id)
        ->where('day_of_week', $dayName)
        ->where('is_available', false)
        ->exists();

    if ($hasUnavailableDay) {
        return response()->json(['message' => 'This day is already marked as unavailable. Cannot add time slots.'], 422);
    }

    try {
        $start = Carbon::createFromFormat('H:i', $request->start_time);
        $end = Carbon::createFromFormat('H:i', $request->end_time);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Invalid time format'], 422);
    }

    $totalMinutes = $start->diffInMinutes($end);

    if ($totalMinutes < $duration || $totalMinutes % $duration !== 0) {
        return response()->json(['message' => 'Time slot must be valid for meeting duration'], 422);
    }

    // التحقق من التعارض
    $conflict = Schedule::where('manager_id', $manager->id)
        ->where('day_of_week', $dayName)
        ->where('is_available', true)
        ->where(function ($query) use ($request) {
            $query->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            });
        })->exists();

    if ($conflict) {
        return response()->json(['message' => 'Conflicting schedule exists for this day'], 422);
    }

    // حساب أقرب تاريخ ليوم الأسبوع المطلوب
    $targetDate = Carbon::now()->next($dayName);

    // إدخال الفترات المتكررة حسب repeat_for_weeks
    for ($i = 0; $i < $repeatWeeks; $i++) {
        $date = $targetDate->copy()->addWeeks($i); // كل أسبوع نفس اليوم

        Schedule::create([
            'manager_id' => $manager->id,
            'day_of_week' => $dayName,
            'date' => $date->toDateString(),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_available' => true,
            'repeat_for_weeks' => 1,
            'meeting_duration_1' => $duration,
            'meeting_duration_2' => 60,
        ]);
    }

    // بعد التكرار، نضيف يوم غير متاح بالتاريخ التالي
    // $finalUnavailableDate = $targetDate->copy()->addWeeks($repeatWeeks);

    // Schedule::create([
    //     'manager_id' => $manager->id,
    //     'day_of_week' => $dayName,
    //     'date' => $finalUnavailableDate->toDateString(),
    //     'start_time' => '00:00',
    //     'end_time' => '23:59',
    //     'is_available' => false,
    //     'repeat_for_weeks' => 1,
    //     'meeting_duration_1' => 30,
    //     'meeting_duration_2' => 60,
    // ]);

    return response()->json(['message' => 'Schedule added successfully with precise weekly dates'], 201);
}

public function updateSchedule(Request $request, $schedule_id)
{
    $validator = Validator::make($request->all(), [
        'start_time' => 'nullable|date_format:H:i',
        'end_time' => 'nullable|date_format:H:i|after:start_time',
        'repeat_for_weeks' => 'nullable|int|min:1',
        'is_available' => 'nullable|boolean',
        'meeting_duration' => 'sometimes|in:30,60',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $manager = Auth::guard('manager')->user();
    if (!$manager) {
        return response()->json(['message' => 'Manager not authenticated'], 401);
    }

    $original = Schedule::find($schedule_id);
    if (!$original) {
        return response()->json(['message' => 'Schedule not found'], 404);
    }

    if ($original->manager_id !== $manager->id) {
        return response()->json(['message' => 'This schedule is not for this manager'], 403);
    }

    $day = $original->day_of_week;
    $newStart = $request->start_time ?? $original->start_time;
    $newEnd = $request->end_time ?? $original->end_time;
    $newRepeat = (int) ($request->repeat_for_weeks ?? $original->repeat_for_weeks);
    $newIsAvailable = $request->is_available ?? $original->is_available;
    $duration = (int) ($request->meeting_duration ?? $original->meeting_duration_1);

    try {
        $startCarbon = Carbon::createFromFormat('H:i', $newStart);
        $endCarbon = Carbon::createFromFormat('H:i', $newEnd);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Invalid time format'], 422);
    }

    $totalMinutes = $startCarbon->diffInMinutes($endCarbon, false); // false = ممكن يطلع بالسالب إذا الوقت غلط
  if ($newIsAvailable) {
    if ($totalMinutes <= 0) {
        return response()->json(['message' => 'End time must be after start time'], 422);
    }

    if ($totalMinutes < $duration) {
        return response()->json(['message' => 'The time range is shorter than the meeting duration'], 422);
    }

    if ($totalMinutes % $duration !== 0) {
        return response()->json([
            'message' => "The time range ($totalMinutes minutes) must be divisible by the new meeting duration ($duration minutes)"
        ], 422);
    }
}


    $dayMap = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];

    $dayNumber = $dayMap[strtolower($day)];
    $today = Carbon::now();
    $currentDayNumber = $today->dayOfWeek;
    $daysToAdd = ($dayNumber - $currentDayNumber + 7) % 7;
    $baseDate = $today->copy()->addDays($daysToAdd);

    // الحالة 1: من متاح -> غير متاح
    if ($original->is_available && !$newIsAvailable) {
        Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $day)
            ->whereDate('date', '>=', now()->toDateString())
            ->delete();

        $changedSchedules = [];
        for ($i = 0; $i < $newRepeat; $i++) {
            $date = $baseDate->copy()->addWeeks($i);

            $schedule = Schedule::create([
                'manager_id' => $manager->id,
                'day_of_week' => $day,
                'date' => $date->toDateString(),
                'start_time' => '00:00',
                'end_time' => '23:59',
                'is_available' => false,
                'repeat_for_weeks' => 1,
                'meeting_duration_1' => 30,
                'meeting_duration_2' => 60,
            ]);

            $changedSchedules[] = $schedule;
        }

        return response()->json(['message' => 'Schedule updated to unavailable', 'data' => $changedSchedules], 200);
    }
    // تعديل الغير متاح

       if (!$original->is_available && !$newIsAvailable) {
        Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $day)
            ->whereDate('date', '>=', now()->toDateString())
            ->delete();

        $changedSchedules = [];
        for ($i = 0; $i < $newRepeat; $i++) {
            $date = $baseDate->copy()->addWeeks($i);

            $schedule = Schedule::create([
                'manager_id' => $manager->id,
                'day_of_week' => $day,
                'date' => $date->toDateString(),
                'start_time' => '00:00',
                'end_time' => '23:59',
                'is_available' => false,
                'repeat_for_weeks' => 1,
                'meeting_duration_1' => 30,
                'meeting_duration_2' => 60,
            ]);

            $changedSchedules[] = $schedule;
        }

        return response()->json(['message' => 'Schedule updated to unavailable', 'data' => $changedSchedules], 200);
    }
    // الحالة 2: من غير متاح -> متاح
    if (!$original->is_available && $newIsAvailable) {
        for ($i = 0; $i < $newRepeat; $i++) {
            $targetDate = $baseDate->copy()->addWeeks($i)->toDateString();

            $conflict = Schedule::where('manager_id', $manager->id)
                ->where('day_of_week', $day)
                ->where('is_available', true)
                ->whereDate('date', $targetDate)
                ->where(function ($query) use ($newStart, $newEnd) {
                    $query->where(function ($q) use ($newStart, $newEnd) {
                        $q->where('start_time', '<', $newEnd)
                            ->where('end_time', '>', $newStart);
                    });
                })
                ->exists();

            if ($conflict) {
                return response()->json(['message' => 'Schedule conflict exists on ' . $targetDate], 409);
            }
        }

        Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $day)
            ->where('is_available', false)
            ->whereDate('date', '>=', now()->toDateString())
            ->delete();

        $changedSchedules = [];
        for ($i = 0; $i < $newRepeat; $i++) {
            $date = $baseDate->copy()->addWeeks($i)->toDateString();

            $schedule = Schedule::create([
                'manager_id' => $manager->id,
                'day_of_week' => $day,
                'date' => $date,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'meeting_duration_1' => $duration,
                'meeting_duration_2' => 60,
                'repeat_for_weeks' => 1,
                'is_available' => true,
            ]);

            $changedSchedules[] = $schedule;
        }

        return response()->json(['message' => 'Schedule updated to available', 'data' => $changedSchedules], 200);
    }

    // الحالة 3: تعديل على يوم متاح
    if ($original->is_available && $newIsAvailable) {
        for ($i = 0; $i < $newRepeat; $i++) {
            $targetDate = $baseDate->copy()->addWeeks($i)->toDateString();
$conflict = Schedule::where('manager_id', $manager->id)
    ->where('day_of_week', $day)
    ->where('is_available', true)
    ->whereDate('date', $targetDate)
    ->where(function ($query) use ($newStart, $newEnd) {
        $query->where(function ($q) use ($newStart, $newEnd) {
            $q->where('start_time', '<', $newEnd)
              ->where('end_time', '>', $newStart);
        });
    })
    // استثناء السجل الأصلي وتكراراته
    ->where(function ($query) use ($original) {
        $query->where('start_time', '!=', $original->start_time)
              ->orWhere('end_time', '!=', $original->end_time);
    })
    ->exists();


            if ($conflict) {
                return response()->json(['message' => 'Schedule conflict exists on ' . $targetDate], 409);
            }
        }

        Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $day)
            ->where('start_time', $original->start_time)
            ->where('end_time', $original->end_time)
            ->whereDate('date', '>=', now()->toDateString())
            ->delete();

        $changedSchedules = [];
        for ($i = 0; $i < $newRepeat; $i++) {
            $date = $baseDate->copy()->addWeeks($i)->toDateString();

            $schedule = Schedule::create([
                'manager_id' => $manager->id,
                'day_of_week' => $day,
                'date' => $date,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'meeting_duration_1' => $duration,
                'meeting_duration_2' => 60,
                'repeat_for_weeks' => 1,
                'is_available' => true,
            ]);

            $changedSchedules[] = $schedule;
        }

        return response()->json(['message' => 'Schedule updated successfully', 'data' => $changedSchedules], 200);
    }

    return response()->json(['message' => 'Nothing to update'], 400);
}



public function viewManagerSchedule()
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

        $permission = Permission::where('name', 'view_calendar')->first();
        if (!$permission || !$assistant->permissions->contains($permission->id)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        $managerId = $linkedManager->id;
    } else {
        $managerId = $manager->id;
    }

    $schedules = Schedule::where('manager_id', $managerId)
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    $groupedByDate = [];

    foreach ($schedules as $schedule) {
        $date = $schedule->date;
        $dayOfWeek = $schedule->day_of_week;
        $duration = $schedule->meeting_duration_1;

        try {
            $start = Carbon::parse($schedule->start_time)->setSeconds(0);
            $end = Carbon::parse($schedule->end_time)->setSeconds(0);
        } catch (\Exception $e) {
            continue;
        }

        $repeatedCount = Schedule::where('manager_id', $managerId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', $schedule->start_time)
            ->where('end_time', $schedule->end_time)
            ->count();

        // جلب المواعيد المحجوزة لهاليوم
        $appointments = Appointment::where('manager_id', $managerId)
            ->whereDate('date', $date)
            ->get();

        $timeSlots = [];
        if ($schedule->is_available) {
            while ($start->lt($end)) {
                $slotStart = $start->format('H:i');
                $slotEnd = $start->copy()->addMinutes($duration)->format('H:i');

                if ($start->copy()->addMinutes($duration)->gt($end)) break;

                // تحقق إذا الوقت محجوز
                $isBooked = $appointments->contains(function ($appointment) use ($slotStart, $slotEnd) {
                    return (
                        ($appointment->start_time <= $slotStart && $appointment->end_time > $slotStart) || // يبدأ قبل وينتهي بعد بداية slot
                        ($appointment->start_time < $slotEnd && $appointment->end_time >= $slotEnd)   || // يتقاطع مع نهاية slot
                        ($appointment->start_time >= $slotStart && $appointment->end_time <= $slotEnd) // يقع كلياً ضمن slot
                    );
                });

                if (!$isBooked) {
                    $timeSlots[] = [
                        'from' => $slotStart,
                        'to' => $slotEnd,
                    ];
                }

                $start->addMinutes($duration);
            }
        } else {
            $timeSlots[] = [
                'from' => '00:00',
                'to' => '23:59',
            ];
        }

        if (!isset($groupedByDate[$date])) {
            $groupedByDate[$date] = [
                'id' => $schedule->id,
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'meeting_duration' => $schedule->is_available ? $schedule->meeting_duration_1 : null,
                'is_available' => $schedule->is_available,
                'repeated_count' => $repeatedCount,
                'slots' => []
            ];
        }

        $groupedByDate[$date]['slots'][] = [
            'time_slots' => $timeSlots,
        ];
    }

    $result = collect($groupedByDate)->sortBy('date')->values()->toArray();

    return response()->json($result, 200);
}






}
