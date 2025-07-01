<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {


        $manager=Manager::create([
            'email'=>'firstmanager@gmail.com',
            'password'=>Hash::make('123456'),
           'department'=>'manager',
        ]);

                $job = Job::create([
    'title' => 'Super Admin Job',
    'manager_id'=> $manager->id
]);
        User::create([
    'first_name' => 'Super',
    'last_name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('secret'),
    'manager_id' => $manager->id,
    'job_id' => $job->id,
]);
    }
}
