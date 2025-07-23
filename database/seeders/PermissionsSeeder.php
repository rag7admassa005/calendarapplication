<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $permissions = [
            [
                'name' => 'accept_appointment',
                'description' => 'Can accept appointments',
            ],
            [
                'name' => 'reject_appointment',
                'description' => 'Can reject appointments',
            ],
            [
                'name' => 'reschedule_appointment',
                'description' => 'Can reschedule appointments',
            ],
            [
                'name' => 'view_calendar',
                'description' => 'Can view the calendar',
            ],
            [
                'name' => 'view_users',
                'description' => 'Can view all users',
            ],
            [
                'name' => 'view_invitations',
                'description' => 'Can view user invitations to the manager',
            ],
            [
                'name' => 'invite_users',
                'description' => 'Can invite users to appointments',
            ],
            [
                'name' => 'add_notes',
                'description' => 'Can add notes to appointments',
            ],
            [
                'name' => 'view_appointment_requests',
                'description' => 'Can view appointment requests',
            ],
             [
                'name' => 'edit_notes',
                'description' => 'Can edit notes ',
            ],
             [
                'name' => 'delete_notes',
                'description' => 'Can delete notes',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

    }
    
}
