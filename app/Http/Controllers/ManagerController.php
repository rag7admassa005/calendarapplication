<?php

namespace App\Http\Controllers;

use App\Mail\ManagerInvitationMail;
use App\Models\Job;
use App\Models\Manager;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ManagerController extends Controller
{
  public function setManagerPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'code' => 'required|string|size:6',
        'new_password' => 'required|string|min:6|confirmed',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $manager = Manager::where('email', $request->email)->first();

    if (!$manager) {
        return response()->json(['message' => 'Manager not found.'], 404);
    }

    if ($manager->email_verified_at) {
        return response()->json(['message' => 'Email already verified.'], 400);
    }

    $now = now();
    $expiresAt = Carbon::parse($manager->code_expires_at);

    if ($now->greaterThan($expiresAt)) {
        return response()->json(['message' => 'Verification code has expired.'], 410); // 410 Gone
    }

    if ($manager->verification_code !== $request->code) {
        return response()->json(['message' => 'Invalid verification code.'], 422);
    }

    // تحديث البيانات
    $manager->update([
        'password' => bcrypt($request->new_password),
        'must_change_password' => false,
        'verification_code' => null,
        'code_expires_at' => null,
        'email_verified_at' => now(),
    ]);

    // إصدار توكن JWT
    $token = JWTAuth::fromUser($manager);

    return response()->json([
        'message' => 'Password has set successfully',
        'token' => $token,
        'manager' => $manager
    ]);
}

    public function resendVerificationCode(Request $request)
    {
         $request->validate([
        'email' => 'required|email|exists:managers,email',
    ]);

    $manager = Manager::where('email', $request->email)->first();

    if (!$manager) {
        return response()->json(['message' =>'Manager is not found'], 404);
    }

        if ($manager->email_verified_at) {
            return response([
                "message" => "Email is already verified."
            ], 400);
        }

        $code = rand(100000, 999999);
        $expiresAt = now()->addMinutes(10); // صلاحية الكود ٥ دقايق

        $manager->update([
            'verification_code' => $code,
            'code_expires_at' => $expiresAt,
        ]);

        Mail::to($manager->email)->send(new ManagerInvitationMail($manager));

        return response([
            "message" => "Verification code resent successfully.",
            "manager"=> $manager
        ], 200);
    }

    public function getAvailableUsers()
{
   
    $manager = Auth::guard('manager')->user();
 if(!$manager)
    {
       return response([
                "message" => "Manager is not found"
            ], 400);
        }
    $users = User::where('section_id', $manager->section_id)
        ->whereNull('manager_id') // لم يُربطوا بعد
        ->select('id', 'first_name', 'last_name', 'email')
        ->get()
        ->map(function ($user) use ($manager) {
            return [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
               
            ];
        });

    return response()->json([
        'message' => 'Users retrieved successfully.',
        'users'   => $users,
    ]);
}

public function assignUserToManager(Request $request)
{
    $manager = Auth::guard('manager')->user();

     if (!$manager) {
        return response()->json([
            "message" => "Manager is not found"
        ], 400);
    }
    $validator = Validator::make($request->all(), [
        'user_id' => 'required|exists:users,id',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $user = User::where('id', $request->user_id)
        ->where('section_id', $manager->section_id)
        ->whereNull('manager_id')
        ->first();

    if (!$user) {
        return response()->json(['message' => 'User not found or does not belong to your section or already assigned.'], 404);
    }

    $user->update(['manager_id' => $manager->id]);

    return response()->json(['message' => 'User assigned successfully.']);
}


//    public function managerLogin(Request $request)
// {
//     $validator = Validator::make($request->all(), [
//         'email' => 'required|email',
//         'password' => 'required|string|min:6',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     $manager = Manager::where('email', $request->email)->first();

//     if (!$manager) {
//         return response(['message'=>'manager is not found'], 404);
//     }

//      if ($manager->must_change_password) {
//         return response()->json([
//             'message' =>'You havnt change your password yet !',
//             'status' => 'must_change_password'
//         ], 403);
//     }

//     if (!Hash::check($request->password, $manager->password)) {
//         return response()->json(['message' => 'password is not tru'], 401);
//     }

   

//     // إصدار توكن JWT
//     $token = JWTAuth::fromUser($manager);

//     return response()->json([
//         'message' => 'logged in successfully',
//         'token' => $token,
//         'manager' => $manager
//     ]);
// }


}