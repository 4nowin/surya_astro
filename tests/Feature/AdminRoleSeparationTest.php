<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminRoleSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $webmaster;
    protected Admin $practitioner;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the idempotent AdminSeeder to establish base roles & users
        $this->seed(AdminSeeder::class);

        $this->webmaster = Admin::where('email', 'webmaster@astrosetu.com')->firstOrFail();
        $this->practitioner = Admin::where('email', 'ravinder@astrosetu.com')->firstOrFail();
    }

    public function test_admin_seeder_is_idempotent_and_seeds_expected_roles_and_users(): void
    {
        // 1. Webmaster has Super Admin and Admin roles under admin guard
        $this->assertTrue($this->webmaster->hasRole('Super Admin', 'admin'));
        $this->assertTrue($this->webmaster->hasRole('Admin', 'admin'));
        $this->assertFalse($this->webmaster->hasRole('Astrologer', 'admin'));
        $this->assertTrue($this->webmaster->isSuperAdmin());
        $this->assertFalse($this->webmaster->isPractitioner());

        // 2. Practitioners have Astrologer role only
        $this->assertTrue($this->practitioner->hasRole('Astrologer', 'admin'));
        $this->assertFalse($this->practitioner->hasRole('Super Admin', 'admin'));
        $this->assertFalse($this->practitioner->hasRole('Admin', 'admin'));
        $this->assertFalse($this->practitioner->isSuperAdmin());
        $this->assertTrue($this->practitioner->isPractitioner());

        // Check the other seeded practitioners
        $yashpal = Admin::where('email', 'yashpal@astrosetu.com')->firstOrFail();
        $this->assertTrue($yashpal->hasRole('Astrologer', 'admin'));
        $this->assertFalse($yashpal->isSuperAdmin());

        $suman = Admin::where('email', 'suman@astrosetu.com')->firstOrFail();
        $this->assertTrue($suman->hasRole('Astrologer', 'admin'));
        $this->assertFalse($suman->isSuperAdmin());

        // Total admins should be 4
        $this->assertEquals(4, Admin::count());

        // Re-run seeder - must be completely idempotent without duplicating or altering state
        $this->seed(AdminSeeder::class);

        $this->assertEquals(4, Admin::count());
        $this->assertTrue($this->webmaster->fresh()->isSuperAdmin());
        $this->assertTrue($this->practitioner->fresh()->isPractitioner());
        $this->assertTrue(Hash::check('12345678', $this->webmaster->fresh()->password));
        $this->assertTrue(Hash::check('12345678', $this->practitioner->fresh()->password));
    }

    public function test_gate_before_grants_all_permissions_to_super_admin(): void
    {
        // Act as webmaster (Super Admin)
        $this->actingAs($this->webmaster, 'admin');

        $this->assertTrue(Gate::forUser($this->webmaster)->allows('manage astrologer applications'));
        $this->assertTrue(Gate::forUser($this->webmaster)->allows('user:create'));
        $this->assertTrue(Gate::forUser($this->webmaster)->allows('any:arbitrary_permission'));

        // Act as practitioner
        $this->actingAs($this->practitioner, 'admin');

        $this->assertFalse(Gate::forUser($this->practitioner)->allows('manage astrologer applications'));
        $this->assertFalse(Gate::forUser($this->practitioner)->allows('any:arbitrary_permission'));
    }

    public function test_practitioner_cannot_access_privileged_admin_routes(): void
    {
        // Practitioner logged into admin guard
        $this->actingAs($this->practitioner, 'admin');

        $privilegedRoutes = [
            '/admin/roles',
            '/admin/admins',
            '/admin/permissions',
            '/admin/astrologer-applications',
            '/admin/astrologer',
            '/admin/banner',
            '/admin/pooja',
            '/admin/horoscope',
            '/admin/coupon',
            '/admin/locations',
            '/admin/promoters',
            '/admin/diagnostics/astrology-engine',
            '/admin/diagnostics/ffi-compatibility',
        ];

        foreach ($privilegedRoutes as $route) {
            $response = $this->get($route);
            $response->assertStatus(403);
        }
    }

    public function test_practitioner_cannot_approve_or_reject_listing_applications(): void
    {
        $application = AstrologerApplication::create([
            'admin_id' => $this->practitioner->id,
            'status' => 'pending',
            'experience' => '12 years',
            'price' => 35,
            'call_price' => 40,
            'original_price' => 60,
            'en_name' => 'Pandit Ravinder',
            'en_expertise' => 'Vedic Astrology',
            'en_languages' => 'English, Hindi',
            'en_excerpt' => 'Vedic excerpt',
            'en_description' => 'Full english bio',
            'hi_name' => 'पंडित रविंदर',
            'hi_expertise' => 'वैदिक ज्योतिष',
            'hi_languages' => 'हिंदी, अंग्रेजी',
            'hi_excerpt' => 'वैदिक सारांश',
            'hi_description' => 'पूर्ण हिंदी विवरण',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->practitioner, 'admin');

        // Attempt approve
        $approveResponse = $this->post("/admin/astrologer-applications/{$application->id}/approve");
        $approveResponse->assertStatus(403);

        // Attempt reject
        $rejectResponse = $this->post("/admin/astrologer-applications/{$application->id}/reject", [
            'rejection_reason' => 'Unauthorized rejection attempt',
        ]);
        $rejectResponse->assertStatus(403);

        // Application status must remain pending
        $this->assertEquals('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->reviewed_by);
    }

    public function test_super_admin_can_access_privileged_routes_and_approve_application(): void
    {
        $application = AstrologerApplication::create([
            'admin_id' => $this->practitioner->id,
            'status' => 'pending',
            'experience' => '12 years',
            'price' => 35,
            'call_price' => 40,
            'original_price' => 60,
            'en_name' => 'Pandit Ravinder',
            'en_expertise' => 'Vedic Astrology',
            'en_languages' => 'English, Hindi',
            'en_excerpt' => 'Vedic excerpt',
            'en_description' => 'Full english bio',
            'hi_name' => 'पंडित रविंदर',
            'hi_expertise' => 'वैदिक ज्योतिष',
            'hi_languages' => 'हिंदी, अंग्रेजी',
            'hi_excerpt' => 'वैदिक सारांश',
            'hi_description' => 'पूर्ण हिंदी विवरण',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->webmaster, 'admin');

        // Can access applications index
        $this->get('/admin/astrologer-applications')->assertStatus(200);

        // Can access application details
        $this->get("/admin/astrologer-applications/{$application->id}")->assertStatus(200);

        // Can approve listing application
        $approveResponse = $this->post("/admin/astrologer-applications/{$application->id}/approve");
        $approveResponse->assertRedirect(route('admin.astrologer-applications.show', $application->id));

        $application->refresh();
        $this->assertEquals('approved', $application->status);
        $this->assertEquals($this->webmaster->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        // Astrologer consumer listing created
        $this->assertDatabaseHas('astrologers', [
            'admin_id' => $this->practitioner->id,
            'name' => 'Pandit Ravinder',
        ]);
    }

    public function test_both_super_admin_and_practitioner_can_access_dashboard_with_role_specific_content(): void
    {
        // 1. Super Admin Dashboard View
        $this->actingAs($this->webmaster, 'admin');
        $superAdminDashboard = $this->get('/admin');
        $superAdminDashboard->assertStatus(200);
        $superAdminDashboard->assertSee('Platform Administration Overview');
        $superAdminDashboard->assertSee('Total Practitioners');
        $superAdminDashboard->assertSee('Pending Approvals');
        $superAdminDashboard->assertSee('Approved Astrologers');

        // 2. Practitioner Workspace Dashboard View
        // Create 2 Jajmans linked to this practitioner and 1 linked to another
        $jajman1 = \App\Models\Jajman::create(['name' => 'Jajman One', 'phone' => '9999900001']);
        $jajman1->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        $jajman2 = \App\Models\Jajman::create(['name' => 'Jajman Two', 'phone' => '9999900002']);
        $jajman2->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        $otherPractitioner = Admin::create([
            'name' => 'Other Practitioner',
            'email' => 'otherpractitioner@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $otherPractitioner->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']));
        $otherJajman = \App\Models\Jajman::create(['name' => 'Other Jajman', 'phone' => '9999900003']);
        $otherJajman->professionals()->attach($otherPractitioner->id, ['status' => 'active', 'source' => 'manual']);

        $this->actingAs($this->practitioner, 'admin');
        $practitionerDashboard = $this->get('/admin');
        $practitionerDashboard->assertStatus(200);
        $practitionerDashboard->assertSee('Practitioner Workspace');
        $practitionerDashboard->assertSee('Astro Setu Marketplace Status');
        $practitionerDashboard->assertSee('Services Offered');
        $practitionerDashboard->assertSee('Jajmans (Clients)');
        $practitionerDashboard->assertSee('2'); // only this practitioner's 2 Jajmans
        $practitionerDashboard->assertDontSee('Platform Administration Overview');
    }

    public function test_api_registration_enforces_astrologer_role_without_super_admin_privileges(): void
    {
        $response = $this->postJson('/api/astrologer/astro-register', [
            'name' => 'New Practitioner Test',
            'email' => 'newpractitioner@astrosetu.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'admin',
            ]);

        $newAdmin = Admin::where('email', 'newpractitioner@astrosetu.com')->firstOrFail();
        $this->assertTrue($newAdmin->hasRole('Astrologer', 'admin'));
        $this->assertFalse($newAdmin->hasRole('Super Admin', 'admin'));
        $this->assertFalse($newAdmin->hasRole('Admin', 'admin'));
        $this->assertFalse($newAdmin->isSuperAdmin());
        $this->assertTrue($newAdmin->isPractitioner());

        // New practitioner cannot access privileged routes
        $this->actingAs($newAdmin, 'admin');
        $this->get('/admin/roles')->assertStatus(403);
    }
}
