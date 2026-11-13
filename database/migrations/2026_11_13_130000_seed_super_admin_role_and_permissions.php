<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Establish roles under 'admin' guard
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $legacyAdminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);
        $astrologerRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // 2. Define standard platform permissions under 'admin' guard
        $permissions = [
            'admin:view', 'admin:create', 'admin:edit', 'admin:delete',
            'role:view', 'role:create', 'role:edit', 'role:delete', 'permission:view',
            'astro-setu:applications:view', 'astro-setu:applications:approve', 'astro-setu:applications:reject',
            'astrologer:view', 'astrologer:create', 'astrologer:edit', 'astrologer:delete',
            'user:view', 'user:edit', 'user:delete',
            'pooja:manage', 'horoscope:manage', 'daily-insight-rules:manage',
            'product:manage', 'notification:send', 'banner:manage',
            'gallery:manage', 'coupon:manage', 'location:manage',
            'enquiry:manage', 'settings:manage',
            'retreat:list', 'retreat:create', 'retreat:dashboard',
            'retreat:tickets', 'retreat:orders', 'retreat:check-in',
            'retreat:delete', 'retreat:customize', 'retreat:bookings',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'admin']);
        }

        // Grant all permissions to Super Admin and legacy Admin roles
        $allPermissions = Permission::where('guard_name', 'admin')->get();
        $superAdminRole->syncPermissions($allPermissions);
        $legacyAdminRole->syncPermissions($allPermissions);

        // 3. Ensure webmaster account receives Super Admin role
        $webmaster = Admin::where('email', 'webmaster@astrosetu.com')->first();
        if ($webmaster) {
            $webmaster->assignRole([$superAdminRole, $legacyAdminRole]);
        }

        // 4. Ensure practitioner seed accounts strictly retain only Astrologer role
        $practitioners = Admin::whereIn('email', [
            'ravinder@astrosetu.com',
            'yashpal@astrosetu.com',
            'suman@astrosetu.com',
        ])->get();

        foreach ($practitioners as $practitioner) {
            $practitioner->syncRoles([$astrologerRole]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe down: do not delete user accounts or tables
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
