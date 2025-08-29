<?php

namespace App\Http\Controllers;

use App\Mail\AssistantAssignedMail;
use App\Models\Assistant;
use App\Models\Permission;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AssistantPermissionsAssignedMail;
use App\Mail\AssistantPermissionsUpdatedMail;
use App\Models\AssistantActivity;
use Illuminate\Support\Facades\Auth;
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

        $validator=Validator::make($request->all(),[
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()], 422);
        }

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
     Mail::to($user->email)->send(new AssistantAssignedMail($user, $manager));
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

    // public function assignPermissions(Request $request)
    // {
    //     $manager = auth('manager')->user();

    //     if (!$manager) {
    //         return response()->json(['message' => 'Unauthorized: Manager not logged in.'], 401);
    //     }

    //     $request->validate([
    //         'assistant_id' => 'required|exists:assistants,id',
    //         'permissions' => 'array', 
    //         'permissions.*' => 'string|exists:permissions,name',
    //     ]);

    //     $assistant = Assistant::find($request->assistant_id);

    //     if ($assistant->manager_id !== $manager->id) {
    //         return response()->json(['message' => 'This assistant does not belong to you.'], 403);
    //     }

    //     $defaultPermissions = [
    //         'view_calendar',
    //         'view_users',
    //         'view_invitations',
    //         'view_appointment_requests',
    //     ];

    //     $allPermissions = array_unique(array_merge($defaultPermissions, $request->permissions ?? []));

    //     $permissionIds = Permission::whereIn('name', $allPermissions)->pluck('id');

    //     foreach ($permissionIds as $permissionId) {
    //         DB::table('assistant_permission')->updateOrInsert(
    //             [
    //                 'assistant_id' => $assistant->id,
    //                 'permission_id' => $permissionId,
    //                 'manager_id' => $manager->id,
    //             ],
    //             [
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]
    //         );
    //     }

    //     if ($assistant->user && $assistant->user->email) {
    //         Mail::to($assistant->user->email)->send(
    //             new AssistantPermissionsAssignedMail($assistant, $allPermissions)
    //         );
    //     }
        
    //     return response()->json([
    //         'message' => 'Permissions assigned successfully.',
    //         'assigned_permissions' => $allPermissions,
    //     ]);
    // }

    public function getMyAssistants()
{
    $managerId = auth()->guard('manager')->id();

    if (!$managerId) {
        return response()->json([
            'message' => 'This manager is not found'
        ], 401);
    }

    $assistants = Assistant::with(['user', 'permissions'])
        ->where('manager_id', $managerId)
        ->get();

    return response()->json([
        'message' => 'Assistants with their user info and permissions',
        'assistants' => $assistants
    ], 200);
}


    public function deleteMyAssistant($id)
    {
        $manager = auth()->guard('manager')->user();

        if (!$manager) {
            return response()->json([
                'message' => ' Please login as a manager.'
            ], 401);
        }

        $assistant = Assistant::find($id);

        if (!$assistant) {
            return response()->json([
                'message' => 'Assistant not found.'
            ], 404);
        }

        if ($assistant->manager_id !== $manager->id) {
            return response()->json([
                'message' => 'You are not allowed to delete this assistant'
            ], 403);
        }

        DB::beginTransaction();
    
        try {
            DB::table('assistant_permission')
                ->where('assistant_id', $assistant->id)
                ->delete();

            $assistant->delete();

            DB::commit();

            return response()->json([
                'message' => 'The assistant and all their permissions have been successfully deleted.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error deleting assistant.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeAllPermissions(Request $request)
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

        $defaultPermissions = [
            'view_calendar',
            'view_users',
            'view_invitations',
            'view_appointment_requests',
        ];

        $defaultPermissionIds = Permission::whereIn('name', $defaultPermissions)->pluck('id')->toArray();

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

    // public function removePermissions(Request $request)
    // {
    //     $manager = auth('manager')->user();

    //     if (!$manager) {
    //         return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
    //     }

    //     $validated = $request->validate([
    //         'assistant_id' => 'required|exists:assistants,id',
    //         'permissions' => 'required|array',
    //         'permissions.*' => 'string|exists:permissions,name',
    //     ]);

    //     $assistant = Assistant::find($validated['assistant_id']);

    //     if (!$assistant || $assistant->manager_id !== $manager->id) {
    //         return response()->json(['status' => false, 'message' => 'This assistant does not belong to you.'], 403);
    //     }

    //     $defaultPermissions = [
    //         'view_calendar',
    //         'view_users',
    //         'view_invitations',
    //         'view_appointment_requests',
    //     ];

    //     $invalid = array_intersect($validated['permissions'], $defaultPermissions);
        
    //     if (!empty($invalid)) {
    //         $messages = [];
    //         foreach ($invalid as $permission) {
    //             $messages[] = "The permission '{$permission}' is a default permission and cannot be removed.";
    //         }

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Default permissions cannot be deleted.',
    //             'errors' => $messages
    //         ], 422);
    //     }

    //     $permissionIds = Permission::whereIn('name', $validated['permissions'])->pluck('id');

    //     DB::table('assistant_permission')
    //         ->where('assistant_id', $assistant->id)
    //         ->where('manager_id', $manager->id)
    //         ->whereIn('permission_id', $permissionIds)
    //         ->delete();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'The selected permissions have been successfully deleted',
    //         'removed_permissions' => $validated['permissions']
    //     ]);
    // }
public function getMyProfile()
{
    $assistant = Auth::guard('assistant')->user();

    if (!$assistant) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $assistant = Assistant::with('user', 'permissions')
        ->where('id', $assistant->id)
        ->first();

    if (!$assistant) {
        return response()->json(['message' => 'Assistant not found'], 404);
    }

    return response()->json([
        'message' => 'Assistant profile',
        'user' => [
            'id' => $assistant->user->id,
            'first_name' => $assistant->user->first_name,
            'last_name' => $assistant->user->last_name,
            'email' => $assistant->user->email,
            'phone_number' => $assistant->user->phone_number,
            'image' => $assistant->user->image,
            'address' => $assistant->user->address,
            'date_of_birth' => $assistant->user->date_of_birth,
            'section_id' => $assistant->user->section_id,
            'manager_id' => $assistant->manager_id,
            'job_id' => $assistant->user->job_id,
            'created_at' => $assistant->user->created_at,
            'updated_at' => $assistant->user->updated_at,
            'role' => 'assistant',
            'permissions' => $assistant->permissions->pluck('name'),
        ]
    ], 200);
}

public function setPermissions(Request $request)
{
    $manager = auth('manager')->user();

    if (!$manager) {
        return response()->json(['message' => 'Unauthorized: Manager not logged in.'], 401);
    }

    $validated = $request->validate([
        'assistant_id'   => 'required|exists:assistants,id',
        'permissions'    => 'array',
        'permissions.*'  => 'string|exists:permissions,name',
    ]);

    $assistant = Assistant::find($validated['assistant_id']);

    if (!$assistant || $assistant->manager_id !== $manager->id) {
        return response()->json(['message' => 'This assistant does not belong to you.'], 403);
    }

    // الصلاحيات الافتراضية (دائمة)
    $defaultPermissions = [
        'view_calendar',
        'view_users',
        'view_invitations',
        'view_appointment_requests',
    ];

    // الصلاحيات اللي المدير بعتهم (غير افتراضية)
    $newPermissions = $validated['permissions'] ?? [];

    // نجيب IDs لكل الصلاحيات المطلوبة (افتراضية + المدخل الجديد)
    $allPermissions = array_unique(array_merge($defaultPermissions, $newPermissions));
    $permissionModels = Permission::whereIn('name', $allPermissions)->get();
    $permissionIds = $permissionModels->pluck('id')->toArray();

    // 1) نحذف كل الصلاحيات الحالية للمساعد ما عدا الافتراضية
    DB::table('assistant_permission')
        ->where('assistant_id', $assistant->id)
        ->where('manager_id', $manager->id)
        ->whereNotIn(
            'permission_id',
            Permission::whereIn('name', $defaultPermissions)->pluck('id')
        )
        ->delete();

    // 2) نضيف الصلاحيات الجديدة (افتراضية + اللي بعتهم المدير)
    foreach ($permissionIds as $permissionId) {
        DB::table('assistant_permission')->updateOrInsert(
            [
                'assistant_id'  => $assistant->id,
                'permission_id' => $permissionId,
                'manager_id'    => $manager->id,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    // 3) إرسال إيميل للمساعد إذا عنده حساب مربوط
    if ($assistant->user && $assistant->user->email) {
        Mail::to($assistant->user->email)->send(
            new AssistantPermissionsUpdatedMail($assistant, $allPermissions)
        );
    }

    return response()->json([
        'message' => 'Permissions updated successfully.',
        'assigned_permissions' => $permissionModels->map(function ($p) {
            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description ?? null, 
            ];
        }),
    ]);
}

public function getAssistantActivity(Request $request, $assistantId)
{
    // التحقق من أن المدير مسجل الدخول
    $manager = Auth::guard('manager')->user();
    if (!$manager) {
        return response()->json([
            'message' => 'Unauthorized: Manager not logged in.'
        ], 401);
    }

    // التحقق من وجود المساعد
    $assistant = Assistant::find($assistantId);
    if (!$assistant) {
        return response()->json([
            'message' => 'Assistant not found.'
        ], 404);
    }

    // التحقق أن المساعد مرتبط بهذا المدير
    if ($assistant->manager_id !== $manager->id) {
        return response()->json([
            'message' => 'This assistant does not belong to you.'
        ], 403);
    }

    // جلب النشاطات مع التفاصيل المرتبطة
    $activities = AssistantActivity::with([
        'permission:id,name',
        'relatedTo' // هنا نستخدم morphTo
    ])
    ->where('assistant_id', $assistantId)
    ->orderBy('executed_at', 'desc')
    ->get();

    return response()->json([
        'assistant' => [
            'id' => $assistant->id,
            'user_id' => $assistant->user_id,
            'name' => $assistant->user->name ?? null,
        ],
        'activities' => $activities
    ]);
}


}