<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\Assistant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
      public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'role'              => 'required|in:user,assistant,manager',
            'email'             => 'required|string|email|max:255|unique:users',
            'password'          => 'required|string|min:6|confirmed',
            'phone_number'      => 'nullable|string|unique:users',
            'address'           => 'nullable|string',
            'date_of_birth'     => 'nullable|date',
            'job_id'            => 'required|exists:jobs,id',
            'manager_id'        => 'nullable|exists:users,id',
            'department'        => 'nullable|string', // فقط إذا كان المدير
        ]);
        if($validator->fails())
        {
            return response(['errors'=>$validator->errors(),422]);
        }
         $profileImage=null;
      if($request->hasFile('image'))
      {
        $profileImage=$request->file('image')->store('profile_images','public');
      }
      $code=rand(100000,999999);  

        $user = User::create([
            'first_name'        => $request->first_name,
            'last_name'         => $request->last_name,
            'role'              => $request->role,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'verification_code' => $code ,
            'code_expires_at'   =>now()->addMinute(10),
            'phone_number'      => $request->phone_number,
            'address'           => $request->address,
            'date_of_birth'     => $request->date_of_birth,
            'job_id'            => $request->job_id,
            'manager_id'        => $request->role === 'assistant' ? $request->manager_id : null,
        ]); 
         if ($request->role === 'manager') {
            Manager::create([
                'user_id' => $user->id,
                'department' => $request->department,
            ]);
        }

        // مساعد
        if ($request->role === 'assistant') {
            Assistant::create([
                'user_id' => $user->id,
                'manager_id' => $request->manager_id,
            ]);
            Mail::to($user->email)->send(new VerificationCodeMail($user->name, $code));
    
    return response(['message' => 'Verification code has sent'],201);
       
       
        }}
        public function verifyCode(Request $request,$user_id)
{
     // تحقق من صحة البيانات
     $validator = Validator::make($request->all(), [
        'code' => 'required|string|size:6'
    ]);
    if ($validator->fails()) {
        return response([
            "errors" => $validator->errors()
        ], 422);
    }

    // العثور على المستخدم
    $user = User::find($user_id);

    if (!$user) {
        return response([
            "message" => "User not found."
        ], 404);
    }
    $token = JWTAuth::fromUser($user);
    //1 هل الايميل هذا متحقق منه سابقا
    if($user->email_verified_at)
    {
        return response(['message' => 'Email already verified'], 400);
    }

    //2 شفلي هاد الكود خالصة صلاحيتو شي 
    // قارن بين الوقت الحالي ووقت انتهاء صلاحية الكود في حال الوقت الحالي اكبر اذا الصلاحية منتهية
    $now=Carbon::now();
    $code_expires_at= Carbon::parse($user->code_expires_at);
    if ($now->greaterThan($code_expires_at)) {
        return response([
            "message" => "Verification code has expired."
        ], 410); // 410 Gone
    }
    
    //3 التحقق من الرمز انه صحيح ام لا
       // التحقق من الكود نفسه
       if ($user->verification_code !== $request->input("code")) {
        return response([
            "message" => "Invalid verification code."
        ], 422);
    }

    // إذا الكود صحيح
    $user->update([
        "verification_code" => null,
        "code_expires_at" => null,
        "email_verified_at" => now()
    ]);
    return response([
        "message" => "Email verified successfully.",
        "token"=>$token,
            "id" => $user->id,
            "first_name" => $user->first_name,
            "last_name" => $user->last_name,
            "email" => $user->email,
            "phone_number" => $user->phone_number,
            "address" => $user->address,
            "date_of_birth" => $user->date_of_birth,
            "role" => $user->role,
            "email_verified_at" => $user->email_verified_at,
            "image" => $user->image ? url("storage/".$user->image) : null,
            "job_id"=>$user->job
    ], 200);
}
public function resendVerificationCode($user_id)
{
    $user = User::find($user_id);

    if (!$user) {
        return response([
            "message" => "User not found."
        ], 404);
    }

    if ($user->email_verified_at) {
        return response([
            "message" => "Email is already verified."
        ], 200);
    }

    $code = rand(100000, 999999);
    $expiresAt = now()->addMinutes(10); // صلاحية الكود ٥ دقايق

    $user->update([
        'verification_code' => $code,
        'code_expires_at' => $expiresAt,
    ]);

    Mail::to($user->email)->send(new  VerificationCodeMail($user->name, $code));

    return response([
        "message" => "Verification code resent successfully."
    ], 200);
}

public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        "email" => "required|string",
        "password" => "required|string"
    ]);

    if ($validator->fails()) {
        return response([
            "error" => $validator->errors()
        ], 422);
    }

    $email = $request->input("email");
    $password = $request->input("password");

    $user = User::where("email", $email)->first();

    if (!$user || !Hash::check($password, $user->password)) {
        return response([
            "error" => "erong email or wrong password"
        ], 401);
    }

    // ✅ تحقق من الدور
    if ($user->role === 'user' && !$user->email_verified_at) {
        return response([
            "message" => "need verify",
            "user_id" => $user->id
        ], 403);
    }

    // ✅ توليد الـ JWT Token
    $token = JWTAuth::fromUser($user);

    return response([
        "message" => "done",
        "token" => $token,
        "id" => $user->id,
        "first_name" => $user->first_name,
        "last_name" => $user->last_name,
        "email" => $user->email,
        "phone_number" => $user->phone_number,
        "address" => $user->address,
        "date_of_birth" => $user->date_of_birth,
        "role" => $user->role,
        "email_verified_at" => $user->email_verified_at,
        "image" => $user->image ? url("storage/".$user->image) : null
    ], 200);
}
public function viewProfile(Request $request)
{
    $user = auth()->user(); // استخراج المستخدم المصادق عليه من التوكن

    return response([
        'id' => $user->id,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'phone_number' => $user->phone_number,
        'address' => $user->address,
        'date_of_birth' => $user->date_of_birth,
        'role' => $user->role,
        'email_verified_at' => $user->email_verified_at,
        'image' => $user->image ? url('storage/' . $user->image) : null,
        'job_id' => $user->job_id,
        'manager_id' => $user->manager_id,
    ]);
}



}
