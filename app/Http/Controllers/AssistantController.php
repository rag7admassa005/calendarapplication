<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Permission;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AssistantPermissionsAssignedMail;
use App\Mail\AssistantPermissionsUpdatedMail;

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
}
public function assignPermissions(Request $request)//اضافة صلاحيات او تعديل 
{
    $manager = auth('manager')->user();

    if (!$manager) {
        return response()->json(['message' => 'Unauthorized: Manager not logged in.'], 401);
    }

    $request->validate([
        'assistant_id' => 'required|exists:assistants,id',
        'permissions' => 'array', // أسماء الصلاحيات الإضافية الاختيارية
        'permissions.*' => 'string|exists:permissions,name',
    ]);

    $assistant = Assistant::find($request->assistant_id);

    // تأكيد أن المساعد فعليًا تابع للمدير
    if ($assistant->manager_id !== $manager->id) {
        return response()->json(['message' => 'This assistant does not belong to you.'], 403);
    }

    // الصلاحيات الافتراضية
    $defaultPermissions = [
        'view_calendar',
        'view_users',
        'view_invitations',
        'view_appointment_requests',
    ];

    // دمج الصلاحيات الافتراضية مع الاختيارية
    $allPermissions = array_unique(array_merge($defaultPermissions, $request->permissions ?? []));

    $permissionIds = Permission::whereIn('name', $allPermissions)->pluck('id');

    foreach ($permissionIds as $permissionId) {
        DB::table('assistant_permission')->updateOrInsert(
            [
                'assistant_id' => $assistant->id,
                'permission_id' => $permissionId,
                'manager_id' => $manager->id,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

// إرسال الإيميل للمساعد
if ($assistant->user && $assistant->user->email) {
    Mail::to($assistant->user->email)->send(
        new AssistantPermissionsAssignedMail($assistant, $allPermissions)
    );
}
    return response()->json([
        'message' => 'Permissions assigned successfully.',
        'assigned_permissions' => $allPermissions,
    ]);
}
public function getMyAssistants()
{
    // جلب معرف المدير من التوكن
    $managerId = auth()->guard('manager')->id();

    // تحقق من وجود التوكن وصحته
    if (!$managerId) {
        return response()->json([
            'message' => 'This manager is not found'
        ], 401);
    }

    // جلب المساعدين المرتبطين بالمدير
    $assistants = Assistant::where('manager_id', $managerId)->get();

    return response()->json($assistants);
}

public function deleteMyAssistant($id)
{
    $manager = auth()->guard('manager')->user();

    // تحقق من تسجيل دخول المدير
    if (!$manager) {
        return response()->json([
            'message' => ' Please login as a manager.'
        ], 401);
    }

    // جلب المساعد المعني
    $assistant = Assistant::find($id);

    // تحقق من وجود المساعد
    if (!$assistant) {
        return response()->json([
            'message' => 'Assistant not found.'
        ], 404);
    }

    // تحقق من ملكية المساعد للمدير الحالي
    if ($assistant->manager_id !== $manager->id) {
        return response()->json([
            'message' => 'You are not allowed to delete this assistant'
        ], 403);
    }

    DB::beginTransaction();

  
        // حذف صلاحيات المساعد من جدول الربط
        DB::table('assistant_permission')
            ->where('assistant_id', $assistant->id)
            ->delete();

        // حذف سجل المساعد من الجدول الرئيسي
        $assistant->delete();

        DB::commit();

        return response()->json([
            'message' => 'The assistant and all their permissions have been successfully deleted.`     +
            '
        ], 200);

    } 

public function removeAllPermissions(Request $request)//حذف كامل الصلاحيات عدا الافتراضية 
{
    $manager = auth('manager')->user();

    if (!$manager) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $request->validate([
        'assistant_id' => 'required|exists:assistants,id',
    ]);

    $assistant = Assistant::find($request->assistant_id);

    if (!$assistant || $assistant->manager_id !== $manager->id) {
        return response()->json(['message' => 'This assistant does not belong to you.'], 403);
    }

    // الصلاحيات الافتراضية
    $defaultPermissions = [
        'view_calendar',
        'view_users',
        'view_invitations',
        'view_appointment_requests',
    ];

    // جلب معرفات الصلاحيات الافتراضية
    $defaultPermissionIds = Permission::whereIn('name', $defaultPermissions)->pluck('id')->toArray();

    // حذف كل الصلاحيات ما عدا الافتراضية
    DB::table('assistant_permission')
        ->where('assistant_id', $assistant->id)
        ->where('manager_id', $manager->id)
        ->whereNotIn('permission_id', $defaultPermissionIds)
        ->delete();

    return response()->json([
        'message' => 'Custom permissions removed successfully.',
        'remaining_permissions' => $defaultPermissions
    ]);
}
public function removePermissions(Request $request)
{
    $manager = auth('manager')->user();

    if (!$manager) {
        return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
    }

    $validated = $request->validate([
        'assistant_id' => 'required|exists:assistants,id',
        'permissions' => 'required|array',
        'permissions.*' => 'string|exists:permissions,name',
    ]);

    $assistant = Assistant::find($validated['assistant_id']);

    if (!$assistant || $assistant->manager_id !== $manager->id) {
        return response()->json(['status' => false, 'message' => 'This assistant does not belong to you.'], 403);
    }

    // الصلاحيات الافتراضية
    $defaultPermissions = [
        'view_calendar',
        'view_users',
        'view_invitations',
        'view_appointment_requests',
    ];

    // التحقق من وجود صلاحيات افتراضية ضمن المدخلات
    $invalid = array_intersect($validated['permissions'], $defaultPermissions);
    if (!empty($invalid)) {
        $messages = [];
        foreach ($invalid as $permission) {
          $messages[] = "The permission '{$permission}' is a default permission and cannot be removed.";

        }

        return response()->json([
            'status' => false,
            'message' => 'Default permissions cannot be deleted.',
            'errors' => $messages
        ], 422);
    }

    // جلب معرفات الصلاحيات المطلوب حذفها
    $permissionIds = Permission::whereIn('name', $validated['permissions'])->pluck('id');

    // حذفها من جدول assistant_permission
    DB::table('assistant_permission')
        ->where('assistant_id', $assistant->id)
        ->where('manager_id', $manager->id)
        ->whereIn('permission_id', $permissionIds)
        ->delete();

    return response()->json([
        'status' => true,
        'message' => 'The selected permissions have been successfully deleted',
        'removed_permissions' => $validated['permissions']
    ]);
}

}
