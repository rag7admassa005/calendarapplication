<?php

namespace App\Http\Controllers;

use App\Models\Manager;
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
        'day_of_week' => 'nullable|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
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

    $newDay = $request->day_of_week ?? $original->day_of_week;
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

    $totalMinutes = $startCarbon->diffInMinutes($endCarbon);
    if ($newIsAvailable && ($totalMinutes < $duration || $totalMinutes % $duration !== 0)) {
        return response()->json(['message' => 'Time slot must be valid for meeting duration'], 422);
    }

    // حذف التكرارات القديمة حسب نوع التعديل
    if ($newIsAvailable) {
        // تعديل جدول متاح (نفس اليوم ونفس التوقيت)
        Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $original->day_of_week)
            ->where('start_time', $original->start_time)
            ->where('end_time', $original->end_time)
            ->whereDate('date', '>=', $original->date)
            ->delete();
    } else {
        // من متاح إلى غير متاح → حذف كل الفترات المتاحة لذلك اليوم من تاريخ الأصل فصاعدًا
        Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $original->day_of_week)
            ->where('is_available', true)
            ->whereDate('date', '>=', $original->date)
            ->delete();
    }

    // بدء التكرار من نفس تاريخ السجل الأصلي
    $targetDate = Carbon::parse($original->date)->next($newDay);
    if ($targetDate->isSameDay(Carbon::parse($original->date))) {
        $targetDate = Carbon::parse($original->date);
    }

    if ($newIsAvailable) {
        for ($i = 0; $i < $newRepeat; $i++) {
            $date = $targetDate->copy()->addWeeks($i);
            Schedule::create([
                'manager_id' => $manager->id,
                'day_of_week' => $newDay,
                'date' => $date->toDateString(),
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'is_available' => true,
                'repeat_for_weeks' => 1,
                'meeting_duration_1' => $duration,
                'meeting_duration_2' => 60,
            ]);
        }
    } else {
        // تكرار غير متاح بناء على repeat_for_weeks
        for ($i = 0; $i < $newRepeat; $i++) {
            $date = $targetDate->copy()->addWeeks($i);
            Schedule::create([
                'manager_id' => $manager->id,
                'day_of_week' => $newDay,
                'date' => $date->toDateString(),
                'start_time' => '00:00',
                'end_time' => '23:59',
                'is_available' => false,
                'repeat_for_weeks' => 1,
                'meeting_duration_1' => 30,
                'meeting_duration_2' => 60,
            ]);
        }
    }

    return response()->json(['message' => 'Schedule updated successfully'], 200);
}
public function viewManagerSchedule()
{
    $manager = Auth::guard('manager')->user();
    if (!$manager) {
        return response()->json(['message' => 'Manager not authenticated'], 401);
    }

    $schedules = Schedule::where('manager_id', $manager->id)
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

        // احسب عدد تكرارات نفس اليوم ونفس الفاصل الزمني
        $repeatedCount = Schedule::where('manager_id', $manager->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', $schedule->start_time)
            ->where('end_time', $schedule->end_time)
            ->count();

        // تقسيم الفترات حسب المدة
        $timeSlots = [];
        if ($schedule->is_available) {
            while ($start->lt($end)) {
                $slotStart = $start->format('H:i');
                $slotEnd = $start->copy()->addMinutes($duration)->format('H:i');

                if ($start->copy()->addMinutes($duration)->gt($end)) break;

                $timeSlots[] = [
                    'from' => $slotStart,
                    'to' => $slotEnd,
                ];

                $start->addMinutes($duration);
            }
        } else {
            $timeSlots[] = [
                'from' => '00:00',
                'to' => '23:59',
            ];
        }

        // إذا التاريخ غير موجود بعد، أضفه
        if (!isset($groupedByDate[$date])) {
            $groupedByDate[$date] = [
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'slots' => []
            ];
        }

        // أضف هذه الفترة
        $groupedByDate[$date]['slots'][] = [
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'is_available' => $schedule->is_available,
            'meeting_duration' => $schedule->is_available ? $schedule->meeting_duration_1 : null,
            'repeated_count' => $repeatedCount,
            'time_slots' => $timeSlots,
        ];
    }

    // ترتيب النتائج حسب التاريخ وتحويلها لمصفوفة
    $result = collect($groupedByDate)->sortBy('date')->values()->toArray();

    return response()->json($result, 200);
}




}
