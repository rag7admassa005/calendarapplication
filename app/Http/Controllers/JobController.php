<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class JobController extends Controller
{
    //CRUD for jobs
public function addJob(Request $request)
{
$manager = Auth::guard('manager')->user();
if(!$manager)
{
    return response(['message'=>'manager is not found'],404);
}
    $validator=Validator::make($request->all(),[
    'title'=>'required|string|max:255|unique:jobs,title',
    'description'=>'nullable|string'
    ]);

    if($validator->fails())
    {
        return response(['errors'=>$validator->errors()],422);
    }

    $job=Job::create([
    'manager_id'=>$manager->id,
    'title'=>$request->title,
    'description'=>$request->description,
    
    ]);

  return response()->json([
        'message' => 'Job created successfully.',
        'job' => $job
    ]);
}



public function editJob(Request $request,$job_id)
{
$manager = Auth::guard('manager')->user();
if(!$manager)
{
    return response(['message'=>'manager is not found'],404);
}
    $job=Job::find($job_id);
    if(!$job)
    {
      return response(['message'=>'job is not found'],422);
    }

     if ($job->manager_id !== $manager->id) {
        return response(['message' => 'Unauthorized. You can only edit your own jobs.'], 403);
    }

    $validator=Validator::make($request->all(),[
    'title'=>'nullable|string|max:255',
    'description'=>'nullable|string'
    ]);

    if($validator->fails())
    {
        return response(['errors'=>$validator->errors()],422);
    }
   // هون اذا المدير مابدو يعدل شي يعني دخل فراغ مثلا مالازم يصير الحقل فاضي بس اذا دخل قيم بتتاخد
    $job->update([
   'title' => $request->filled('title') ? $request->title : $job->title,
'description' => $request->filled('description') ? $request->description : $job->description,

    
    ]);

  return response()->json([
        'message' => 'Job updated successfully.',
        'job' => $job
    ]);

}

public function deletJob($job_id)
{
    $manager = Auth::guard('manager')->user();
if(!$manager)
{
    return response(['message'=>'manager is not found'],404);
}
    $job=Job::find($job_id);
    if(!$job)
    {
      return response(['message'=>'job is not found'],422);
    }

     if ($job->manager_id !== $manager->id) {
        return response(['message' => 'Unauthorized. You can only edit your own jobs.'], 403);
    }

    $job->delete();

   
        return response(['message'=>'job deleted successfully']);
    
}
 // المدير يعرض اعماله منعتمد على jwt 
public function myJobs()
{
    $manager = Auth::guard('manager')->user();

    if (!$manager) {
        return response(['message' => 'Unauthorized'], 401);
    }
    $jobs = $manager->jobs()->get();

    return response([
        'message' => 'Jobs retrieved successfully.',
        'jobs' => $jobs
    ]);
}

// المستخدم بدو يشوف اعمال المانجر , تبعا لل id 
public function showJobsByManager($manager_id)
{
    $manager = Manager::find($manager_id);
    if (!$manager) {
        return response(['message' => 'Manager not found'], 404);
    }

    $jobs = $manager->jobs()->get();

    return response()->json([
        'manager' => $manager->only(['id', 'first_name', 'last_name']),
        'jobs' => $jobs
    ]);
}
}
