<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $admin = Admin::create([
            'name' => 'Navgrah Admin', 
            'email' => 'webmaster@navgarah.com',
            'password' => bcrypt('12345678')
        ]);

        // Create Admin Role if it doesn't exist
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);
        $permissions = Permission::pluck('id','id')->all();
        $adminRole->syncPermissions($permissions);
        $admin->assignRole([$adminRole->id]);

        // Create Astrologer Role if not exists
        $astrologerRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // Create 3 astrologers
        $astrologers = [
            [
                'name' => 'Ravinder Sharma',
                'email' => 'ravinder@navgarah.com',
            ],
            [
                'name' => 'Yashpal Sharma',
                'email' => 'yashpal@navgarah.com',
            ],
            [
                'name' => 'Suman Sharma',
                'email' => 'suman@navgarah.com',
            ],
        ];

        foreach ($astrologers as $astro) {
            $user = Admin::create([
                'name' => $astro['name'],
                'email' => $astro['email'],
                'password' => bcrypt('12345678')
            ]);

            $user->assignRole([$astrologerRole->id]);
        }
    }
}
