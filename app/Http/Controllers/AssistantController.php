<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Assistant;
use App\Models\Permission;
use App\Models\AssistantPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssistantController extends Controller
{

public function assignAssistant(Request $request)
{
    $manager = auth('manager')->user();

    if (!$manager) {
        return response()->json([
            'message' => 'Unauthorized: Manager not logged in.'
        ], 401);
    }

    $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $user = User::find($request->user_id);

    // تحقق أن المستخدم فعليًا تابع لنفس المدير
    if ($user->manager_id !== $manager->id) {
        return response()->json([
            'message' => 'This user is not assigned to you.'
        ], 403);
    }

    // تأكد أنو المستخدم مو مساعد مسبقًا
    if (Assistant::where('user_id', $user->id)->exists()) {
        return response()->json([
            'message' => 'This user is already assigned as assistant.'
        ], 409);
    }

    DB::beginTransaction();

    try {
        // إنشاء سجل المساعد
        $assistant = Assistant::create([
            'user_id' => $user->id,
            'manager_id' => $manager->id,
        ]);

        // الصلاحيات الافتراضية
        $defaultPermissions = [
            'view_calendar',
            'view_users',
            'view_invitations',
            'view_appointment_requests',
        ];

        $permissionIds = Permission::whereIn('name', $defaultPermissions)->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('assistant_permission')->insert([
                'assistant_id' => $assistant->id,
                'permission_id' => $permissionId,
                'manager_id' => $manager->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::commit();

        return response()->json([
            'message' => 'Assistant assigned successfully with default permissions.',
            'assistant_id' => $assistant->id
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error assigning assistant.',
            'error' => $e->getMessage()
        ], 500);
    }
}}