<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Job;

class SectionAndJobSeeder extends Seeder
{
    public function run(): void
    {
        $sectionsWithJobs = [
            'Software Development' => [
                [
                    'title' => 'Backend Developer',
                    'description' => 'Responsible for server-side application logic and integration of the work front-end developers do.'
                ],
                [
                    'title' => 'Frontend Developer',
                    'description' => 'Builds the visual and interactive elements of a website or application.'
                ],
                [
                    'title' => 'Full Stack Developer',
                    'description' => 'Works on both the front-end and back-end parts of an application.'
                ],
            ],
            'Graphic Design' => [
                [
                    'title' => 'UI Designer',
                    'description' => 'Designs user interfaces for apps and websites focusing on visual layout.'
                ],
                [
                    'title' => 'Brand Identity Designer',
                    'description' => 'Creates visual identities including logos, color schemes, and typography.'
                ],
                [
                    'title' => 'Motion Graphic Designer',
                    'description' => 'Designs animated graphics for videos and digital interfaces.'
                ],
            ],
            'Healthcare' => [
                [
                    'title' => 'General Practitioner',
                    'description' => 'Provides general medical care to patients, diagnoses and treats illnesses.'
                ],
                [
                    'title' => 'Nurse',
                    'description' => 'Provides nursing care and assists doctors in medical procedures.'
                ],
                [
                    'title' => 'Radiology Technician',
                    'description' => 'Operates imaging equipment to conduct scans for diagnostic purposes.'
                ],
            ],
            'Education' => [
                [
                    'title' => 'Primary School Teacher',
                    'description' => 'Teaches basic subjects to young students in primary grades.'
                ],
                [
                    'title' => 'Subject Tutor',
                    'description' => 'Offers private or group tutoring in specialized subjects like Math or Science.'
                ],
                [
                    'title' => 'Special Education Teacher',
                    'description' => 'Teaches students with learning, mental, emotional, or physical disabilities.'
                ],
            ],
            'Business & Administration' => [
                [
                    'title' => 'Administrative Assistant',
                    'description' => 'Handles office tasks such as scheduling, correspondence, and file management.'
                ],
                [
                    'title' => 'HR Specialist',
                    'description' => 'Manages recruitment, employee relations, and organizational policies.'
                ],
                [
                    'title' => 'Project Manager',
                    'description' => 'Oversees projects from planning to execution and ensures timely delivery.'
                ],
            ],
        ];

        foreach ($sectionsWithJobs as $sectionName => $jobs) {
            $section = Section::create([
                'name' => $sectionName,

            ]);

            foreach ($jobs as $job) {
                Job::create([
                    'section_id' => $section->id,
                    'title' => $job['title'],
                    'description' => $job['description'],
                ]);
            }
        }
    }
}

