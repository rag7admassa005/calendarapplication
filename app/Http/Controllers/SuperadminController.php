<?php

namespace App\Http\Controllers;

use App\Mail\ManagerInvitationMail;
use App\Models\Assistant;
use App\Models\Manager;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class SuperadminController extends Controller
{
//   
public function adminLogin(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string|min:6',
    ]);

    if ($validator->fails()) {
        return response(['errors' => $validator->errors()], 422);
    }

    $email = $request->email;
    $password = $request->password;

    // === 1. تحقق من السوبر أدمن ===
    $superAdmin = User::where('email', $email)->first();
    if ($superAdmin && $superAdmin->email === 'admin@example.com' && Hash::check($password, $superAdmin->password)) {
        $token = JWTAuth::fromUser($superAdmin);
        $superAdmin->role = 'super_admin';
        $superAdmin->token = $token;

        return response()->json([
            'message' => 'Login successful',
            'user' => $superAdmin
        ]);
    }

    // === 2. تحقق من المدير ===
    $manager = Manager::where('email', $email)->first();
    if ($manager) {
        if ($manager->must_change_password) {
            return response()->json([
                'message' => 'You haven’t changed your password yet!',
                'status' => 'must_change_password'
            ], 403);
        }

        if (Hash::check($password, $manager->password)) {
            $customPayload = ['guard' => 'manager'];
            $token = JWTAuth::customClaims($customPayload)->fromUser($manager);
            $manager->role = 'manager';
            $manager->token = $token;

            return response()->json([
                'message' => 'Login successful',
                'user' => $manager
            ]);
        }
    }

    // === 3. تحقق من المساعد ===
    $assistant = Assistant::whereHas('user', function ($query) use ($email) {
        $query->where('email', $email);
    })->with('user')->first();

    if ($assistant && Hash::check($password, $assistant->user->password)) {
        $customPayload = ['guard' => 'assistant'];
        $token = JWTAuth::customClaims($customPayload)->fromUser($assistant->user);
        $assistant->user->role = 'assistant';
        $assistant->user->token = $token;

        return response()->json([
            'message' => 'Login successful',
            'user' => $assistant->user
        ]);
    }

    return response()->json(['message' => 'Invalid credentials'], 401);
}

    public function addManager(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'             => 'required|string|email|max:255|unique:managers',
            'department'        => 'required|string',
            'name'              =>'required|string',
            'image'=>'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',

        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors(), 422]);
        }

          $profileImage = null;
           if ($request->hasFile('image')) {
               $profileImage = $request->file('image')->store('profile_images', 'public');
           }
        $verificationCode = rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        $manager = Manager::create([
            'email'             => $request->email,
            'name'              =>$request->name,
            'image'             =>$profileImage,
            'department'       => $request->department,
            'must_change_password' => true,
            'verification_code' => $verificationCode,
            'code_expires_at' => $expiresAt,
            'password' => bcrypt(Str::random(10)), // مؤقت

        ]);


            Mail::to($manager->email)->send(new ManagerInvitationMail($manager));

  return response()->json([
    'message' => 'Manager created and email sent.',
    'manager' => [
        'email'             => $manager->email,
        'name'              => $manager->name,
        'image'             => $manager->image ? url("storage/" . $manager->image) : null,
        'department'        => $manager->department,
        'must_change_password' => $manager->must_change_password,
        'verification_code' => $manager->verification_code,
        'code_expires_at'   => $manager->code_expires_at,
        'password'          => $manager->password, // تأكد إنك ما تبعتها فعلاً بالإنتاج
    ]
]);
    }


    public function deleteManager($id)
    {
        // نتحقق أن المستخدم هو مدير
        $manager = Manager::find($id);

        if (!$manager) {
            return response(['message' => 'Manager id not found.'], 404);
        }

        $manager->delete();

        return response()->json(['message' => 'Manager deleted successfully.']);
    }


    public function showAllManagers()
    {
        // جلب كل مدراء جدول managers مع معلومات المستخدم والوظيفة
        $managers = Manager::get();

        $result = $managers->map(function ($manager) {

            return [
                'id' => $manager->id,
                'name'=>$manager->name,
                "image" => $manager->image ? url("storage/".$manager->image) : null,
                'email' => $manager->email,
                'email_verified_at' => $manager->email_verified_at,
                'department' => $manager->department,
            ];
        });

        return response()->json($result);
    }


    public function showManager($managerId)
    {
        // نجلب سجل المدير حسب الـ id من جدول managers مع المستخدم والعلاقة job
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }



        return response()->json([
            'id' => $manager->id,  // هنا id من جدول managers
            'email' => $manager->email,
             'name'=>$manager->name,
             "image" => $manager->image ? url("storage/".$manager->image) : null,
            'email_verified_at' => $manager->email_verified_at,
            'department' => $manager->department,
        ]);
    }
}


// if (!$user) {
//         return response()->json(['message' => 'User not found'], 404);
//     }

//     $validator = Validator::make($request->all(), [
//         'first_name'    => 'nullable|string|max:255',
//         'last_name'     => 'nullable|string|max:255',
//         'phone_number'  => 'nullable|string|unique:users,phone_number,' . $user->id,
//         'address'       => 'nullable|string',
//         'date_of_birth' => 'nullable|date',
//         'job_id'        => 'nullable|exists:jobs,id',
//        'manager_id'        => [
//             'required',
//             Rule::exists('users', 'id')->where('role', 'manager'),],
//         'department'    => 'nullable|string',
//         'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     // تهيئة مصفوفة التحديث
//     $updateData = [];

//     // تحديث الحقول فقط إذا كانت موجودة في الطلب
//     if ($request->has('first_name')) {
//         $updateData['first_name'] = $request->first_name;
//     }

//     if ($request->has('last_name')) {
//         $updateData['last_name'] = $request->last_name;
//     }

//     if ($request->has('phone_number')) {
//         $updateData['phone_number'] = $request->phone_number;
//     }

//     if ($request->has('address')) {
//         $updateData['address'] = $request->address;
//     }

//     if ($request->has('date_of_birth')) {
//         $updateData['date_of_birth'] = $request->date_of_birth;
//     }

//     if ($request->has('job_id')) {
//         $updateData['job_id'] = $request->job_id;
//     }

//     // رفع الصورة إن وجدت
//     if ($request->hasFile('image')) {
//         $updateData['image'] = $request->file('image')->store('profile_images', 'public');
//     }

//     // التحقق من الدور
//     if ($user->role === 'manager') {
//         // المدير لا يجب أن يكون له مدير
//         $updateData['manager_id'] = null;

//         if ($request->has('department')) {
//             $updateData['department'] = $request->department;
//         }
//     } else {
//         // المستخدم العادي يمكنه تعديل manager_id فقط
//         if ($request->has('manager_id')) {
//             $updateData['manager_id'] = $request->manager_id;
//         }
//     }

//     // تنفيذ التحديث
//     $user->update($updateData);

//     return response()->json([
//         'id' => $user->id,
//         'first_name' => $user->first_name,
//         'last_name' => $user->last_name,
//         'email' => $user->email,
//         'phone_number' => $user->phone_number,
//         'address' => $user->address,
//         'date_of_birth' => $user->date_of_birth,
//         'role' => $user->role,
//         'email_verified_at' => $user->email_verified_at,
//         'image' => $user->image ? url('storage/' . $user->image) : null,
//         'job_id' => $user->job_id,
//         'manager_id' => $user->manager_id,
//         'department' => $user->department,
//     ], 200);
// }
// اذا المستخدم متذكر كلمة السر ممكن يرجع يعينها
// public function resetPassword(Request $request)
// {
//  $user = Auth::guard('api')->user();

//     if (!$user) {
//         return response()->json(['message' => 'User not found'], 404);
//     }
//      $validator = Validator::make($request->all(), [

//         'current_password' => 'required|string|min:6',
//         'new_password' => 'required|string|min:6|confirmed',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//      if (!Hash::check($request->current_password, $user->password)) {
//         return response()->json(['error' => 'Current password is incorrect.'], 401);
//     }

//     $user->update([
//         'password' => Hash::make($request->new_password),
//         'must_change_password' => false,
//     ]);

//     return response()->json(['message' => 'Password updated successfully.'], 200);
// }

// // اذا المستخدم نسيان كلمة السر بينبعت رمز تحقق على ايميلو
// public function forgetPassword(Request $request)
// {
//      $request->validate([
//         'email' => 'required|email|exists:users,email',
//     ]);

//      $user = User::where('email', $request->email)->first();
//     $code = rand(100000, 999999);

// Joudy, [6/30/2025 9:46 AM]
// $user->update([
//              'verification_code' => $code ,
//             'code_expires_at'   =>now()->addMinute(10),
//     ]);

//  Mail::to($user->email)->send(new VerificationCodeMail($user->name, $code));
   
//  return response()->json(['message' => 'Reset code sent to your email.']);
// }

// public function confirmResetCode(Request $request)
// {
//     $request->validate([
//         'email' => 'required|email|exists:users,email',
//         'reset_code' => 'required|string|size:6',
//         'new_password' => 'required|string|min:6|confirmed',
//     ]);

//     $user = User::where('email', $request->email)
//                 ->where('verification_code', $request->reset_code)
//                 ->where('code_expires_at', '>', now())
//                 ->first();

//     if (!$user) {
//         return response()->json(['message' => 'Invalid or expired reset code'], 400);
//     }

    
//     // تعيين كلمة السر الجديدة
//     $user->password = Hash::make($request->new_password);
//     $user->verification_code = null;
//     $user->code_expires_at = null;
//     $user->must_change_password = false;
//     $user->save();

//     return response()->json(['message' => 'Password has been reset successfully.']);
// }

// }