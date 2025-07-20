<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use App\Models\Manager;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    public function register(Request $request)
    {
       $validator = Validator::make($request->all(), [
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|string|email|max:255|unique:users',
            'password'          => 'required|string|min:6|confirmed',
            'phone_number'      => 'nullable|string|unique:users',
            'address'           => 'nullable|string',
            'date_of_birth'     => 'nullable|date',
            'job_id'            => 'required|exists:jobs,id',
            'manager_id'        => 'required|exists:managers,id',// تحثث ان المستخدم الذي تم اختياره هو فعلا مدير
             'image'=>'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $profileImage = null;
           if ($request->hasFile('image')) {
               $profileImage = $request->file('image')->store('profile_images', 'public');
}
         $verificationCode = rand(100000, 999999);
              $user = User::create([
            'first_name'        => $request->first_name,
            'last_name'         => $request->last_name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'verification_code' => $verificationCode,
            'code_expires_at'   =>now()->addMinute(10),
            'phone_number'      => $request->phone_number,
            'address'           => $request->address,
            'date_of_birth'     => $request->date_of_birth,
            'job_id'            => $request->job_id,
            'manager_id'        => $request->manager_id,
            'image'             => $profileImage,
        ]); 
        
        Mail::to($user->email)->send(new VerificationCodeMail($verificationCode, $user));
        $token = JWTAuth::fromUser($user);
    
        return response()->json([
            'message' => 'Verify your email using the code sent.'], 201);
    }

    public function verifyCode(Request $request,$user_id)
{
 
    //  التحقق من المدخلات
    $validator = Validator::make($request->all(), [
        'code' => 'required|string|size:6',
    ]);

    if ($validator->fails()) {
        return response([
            'errors' => $validator->errors(),
        ], 422);
    }

    //  العثور على المستخدم
    $user = User::find($user_id);
    if (!$user) {
        return response([
            'message' => 'User not found.',
        ], 404);
    }

  $token = JWTAuth::fromUser($user);
    // تحقق من الايميل 
    if($user->email_verified_at)
    {
        return response(['message' => 'Email already verified'], 400);
    }
// صلاحية التوكن   
    $now=Carbon::now();
    $code_expires_at= Carbon::parse($user->code_expires_at);
    if ($now->greaterThan($code_expires_at)) {
        return response([
            "message" => "Verification code has expired."
        ], 410); 
    }
    
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
            "job_id"=>$user->job,
            'manager_id'=> $request->manager_id,
    ], 200);
}

public function resendVerificationCode($user_id)
{
    // 1. العثور على المستخدم
    $user = User::find($user_id);

    if (!$user) {
        return response()->json(['message' => 'User not found.'], 404);
    }

    // 2. التحقق إذا كان الإيميل موثق مسبقاً
    if ($user->email_verified_at) {
        return response()->json(['message' => 'Email already verified.'], 400);
    }

    // 3. توليد كود جديد
    $verificationCode = rand(100000, 999999);

    // 4. تحديث الكود وتاريخ الانتهاء
    $user->update([
        'verification_code' => $verificationCode,
        'code_expires_at'   => now()->addMinutes(10),
    ]);

    // 5. إرسال الكود إلى البريد الإلكتروني
   
        Mail::to($user->email)->send(new VerificationCodeMail($verificationCode, $user));

    // 6. إعادة الاستجابة
    return response()->json([
        'message' => 'Verification code resent successfully.',
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
            "error" => "Wrong email or password"
        ], 401);
    }
    if (!$user->email_verified_at) {
        return response([
            "message" => "You must verify your email.",
            "user_id" => $user->id
        ], 403);
    }
    $token = JWTAuth::fromUser($user);

    return response([
        "message" => "Login successful.",
        "token" => $token,
        "id" => $user->id,
        "first_name" => $user->first_name,
        "last_name" => $user->last_name,
        "email" => $user->email,
        "phone_number" => $user->phone_number,
        "address" => $user->address,
        "date_of_birth" => $user->date_of_birth,
        "email_verified_at" => $user->email_verified_at,
        "image" => $user->image ? url("storage/" . $user->image) : null,
        "job_id" => $user->job_id,
        "manager_id" => $user->manager_id,
    ], 200);
}
public function viewProfile(Request $request)
{
    $user = Auth::guard('api')->user();

    return response([
        'id' => $user->id,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'phone_number' => $user->phone_number,
        'address' => $user->address,
        'date_of_birth' => $user->date_of_birth,
        'email_verified_at' => $user->email_verified_at,
        'image' => $user->image ? url('storage/' . $user->image) : null,
        'job_id' => $user->job_id,
        'manager_id' => $user->manager_id,
    ]);
}
public function updateProfile(Request $request)
{
    $user = Auth::guard('api')->user();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    $validator = Validator::make($request->all(), [
        'first_name'    => 'nullable|string|max:255',
        'last_name'     => 'nullable|string|max:255',
        'phone_number'  => 'nullable|string|unique:users,phone_number,' . $user->id,
        'address'       => 'nullable|string',
        'date_of_birth' => 'nullable|date',
        'job_id'        => 'nullable|exists:jobs,id',
        'manager_id'    => 'nullable|exists:managers,id',
        'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $updateData = [];

    foreach (['first_name', 'last_name', 'phone_number', 'address', 'date_of_birth', 'job_id', 'manager_id'] as $field) {
        if ($request->has($field)) {
            $updateData[$field] = $request->$field;
        }
    }

    if ($request->hasFile('image')) {
        $updateData['image'] = $request->file('image')->store('profile_images', 'public');
    }

    $user->update($updateData);

    return response()->json([
        'message' => 'Profile updated successfully',
        'user' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'address' => $user->address,
            'date_of_birth' => $user->date_of_birth,
            'email_verified_at' => $user->email_verified_at,
            'image' => $user->image ? url('storage/' . $user->image) : null,
            'job_id' => $user->job_id,
            'manager_id' => $user->manager_id,
        ]
    ], 200);
}
public function forgetPassword(Request $request)
{
    // 1. التحقق من البريد
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    // 2. جلب المستخدم بناءً على البريد
    $user = User::where('email', $request->email)->first();

    // 3. إنشاء كود تحقق جديد
    $verificationCode = rand(100000, 999999);

    // 4. تحديث بيانات الكود وتاريخ الانتهاء
    $user->update([
        'verification_code' => $verificationCode,
        'code_expires_at' => now()->addMinutes(10),
    ]);

    // 5. إرسال الكود إلى الإيميل
    Mail::to($user->email)->send(new VerificationCodeMail($verificationCode, $user));

    // 6. استجابة ناجحة
    return response()->json([
        'message' => 'Verification code sent to your email. Please check your inbox.',
        'user_id' => $user->id, // لتحديد المستخدم لاحقاً في تأكيد الكود وإعادة التعيين
    ], 200);
}
public function confirmResetCode(Request $request)
{
    // 1. التحقق من صحة البيانات
    $request->validate([
        'email'         => 'required|email|exists:users,email',
        'reset_code'    => 'required|string|size:6',
        'new_password'  => 'required|string|min:6|confirmed',
    ]);

    // 2. البحث عن المستخدم والتحقق من الكود وتاريخ انتهاءه
    $user = User::where('email', $request->email)
                ->where('verification_code', $request->reset_code)
                ->where('code_expires_at', '>', now())
                ->first();

    if (!$user) {
        return response()->json([
            'message' => 'Invalid or expired reset code.'
        ], 400);
    }

    // 3. تحديث كلمة المرور وإلغاء الكود
    $user->update([
        'password' => Hash::make($request->new_password),
        'verification_code' => null,
        'code_expires_at' => null,
    ]);

    return response()->json([
        'message' => 'Password has been reset successfully.'
    ], 200);
}
public function logout(Request $request)
{
    try {
        // الحصول على المستخدم من التوكن
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // إبطال التوكن
        JWTAuth::invalidate(JWTAuth::getToken());

        // حذف المستخدم
        $user->delete();

        return response()->json(['message' => 'User logged out and deleted successfully.'], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Something went wrong.',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function listManagers()
{
    $managers = Manager::select('id', 'email')->get();

    return response()->json([
        'managers' => $managers
    ], 200);
}
}