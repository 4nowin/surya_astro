<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Create or find roles under 'admin' guard
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $legacyAdminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);
        $astrologerRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // 3. Define platform administrative permissions under 'admin' guard
        $permissions = [
            // Platform / Admin Account Management
            'admin:view', 'admin:create', 'admin:edit', 'admin:delete',
            // Roles & Permissions Management
            'role:view', 'role:create', 'role:edit', 'role:delete', 'permission:view',
            // Astro Setu Applications Review & Verification Queue
            'astro-setu:applications:view', 'astro-setu:applications:approve', 'astro-setu:applications:reject',
            // Published Astro Setu Astrologers Management
            'astrologer:view', 'astrologer:create', 'astrologer:edit', 'astrologer:delete',
            // Consumers Management
            'user:view', 'user:edit', 'user:delete',
            // Platform Services & Features
            'pooja:manage', 'horoscope:manage', 'daily-insight-rules:manage',
            'product:manage', 'notification:send', 'banner:manage',
            'gallery:manage', 'coupon:manage', 'location:manage',
            'enquiry:manage', 'settings:manage',
            // Retreat Permissions (legacy support)
            'retreat:list', 'retreat:create', 'retreat:dashboard',
            'retreat:tickets', 'retreat:orders', 'retreat:check-in',
            'retreat:delete', 'retreat:customize', 'retreat:bookings',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'admin']);
        }

        // Super Admin and legacy Admin roles get all platform permissions
        $allAdminPermissions = Permission::where('guard_name', 'admin')->get();
        $superAdminRole->syncPermissions($allAdminPermissions);
        $legacyAdminRole->syncPermissions($allAdminPermissions);

        // 4. Create or find the Platform Super Admin (webmaster@astrosetu.com)
        // Never overwrite existing passwords on rerun
        $webmaster = Admin::where('email', 'webmaster@astrosetu.com')->first();
        if (!$webmaster) {
            $webmaster = Admin::create([
                'name' => 'Navgrah Super Admin',
                'email' => 'webmaster@astrosetu.com',
                'password' => bcrypt('12345678'),
            ]);
        }

        // Assign Super Admin and legacy Admin roles to webmaster
        $webmaster->assignRole([$superAdminRole, $legacyAdminRole]);

        // 5. Seed standard practitioner accounts with ONLY Astrologer role
        $astrologers = [
            [
                'name' => 'Ravinder Sharma',
                'email' => 'ravinder@astrosetu.com',
            ],
            [
                'name' => 'Yashpal Sharma',
                'email' => 'yashpal@astrosetu.com',
            ],
            [
                'name' => 'Suman Sharma',
                'email' => 'suman@astrosetu.com',
            ],
        ];

        foreach ($astrologers as $astro) {
            $practitioner = Admin::where('email', $astro['email'])->first();
            if (!$practitioner) {
                $practitioner = Admin::create([
                    'name' => $astro['name'],
                    'email' => $astro['email'],
                    'password' => bcrypt('12345678'),
                ]);
            }

            // Strictly assign only the Astrologer role under admin guard
            // Practitioners never receive Super Admin or Admin roles
            $practitioner->syncRoles([$astrologerRole]);
        }
    }
}
