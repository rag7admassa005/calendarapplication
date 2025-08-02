<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Models\Manager;
use App\Models\Section;
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


            // أولاً: إنشاء قسم (Section)
        $section = Section::create([
            'name' => 'Administration',
        ]);

        // ثانياً: إنشاء مدير مرتبط بالقسم
        $manager = Manager::create([
            'name' => 'Super Manager',
            'email' => 'superadmin@gmail.com',
            'password' => Hash::make('secret'),
            'section_id' => $section->id,
        ]);

        // ثالثاً: إنشاء وظيفة مرتبطة بنفس القسم
        $job = Job::create([
            'title' => 'Super Admin Job',
            'description' => 'Top level job for admin tasks',
            'section_id' => $section->id,
        ]);

        // رابعاً: إنشاء مستخدم مرتبط بالقسم والوظيفة والمدير
        User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
            'section_id' => $section->id,
            'job_id' => $job->id,
            'manager_id' => $manager->id,
        ]);
    }
}
