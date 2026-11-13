<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use App\Models\ProfessionalPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AstroSetuListingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $astrologerAdmin;
    protected Admin $secondAstrologerAdmin;
    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);
        $astroRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // Super Admin (can review)
        $this->superAdmin = Admin::create([
            'name' => 'Platform Admin',
            'email' => 'admin@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->superAdmin->assignRole($adminRole);

        // Practitioner 1
        $this->astrologerAdmin = Admin::create([
            'name' => 'Pandit Dev',
            'email' => 'dev@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->astrologerAdmin->assignRole($astroRole);

        // Practitioner 2
        $this->secondAstrologerAdmin = Admin::create([
            'name' => 'Acharya Mohan',
            'email' => 'mohan@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->secondAstrologerAdmin->assignRole($astroRole);
    }

    public function test_login_succeeds_with_valid_credentials_and_returns_sanctum_token(): void
    {
        $response = $this->postJson('/api/astrologer/astro-login', [
            'email' => 'dev@astrosetu.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'admin' => ['id', 'email', 'name'],
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_401_on_invalid_credentials(): void
    {
        $response = $this->postJson('/api/astrologer/astro-login', [
            'email' => 'dev@astrosetu.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'The provided credentials are incorrect.',
            ]);
    }

    public function test_login_returns_422_on_missing_fields(): void
    {
        $response = $this->postJson('/api/astrologer/astro-login', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_practitioner_can_query_app_listing_status(): void
    {
        $token = $this->astrologerAdmin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/astrologer/app-listing');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'not_applied',
                'has_application' => false,
                'is_published' => false,
            ]);
    }

    public function test_application_submission_creates_pending_record_without_publishing_to_astrologers(): void
    {
        $token = $this->astrologerAdmin->createToken('test-token')->plainTextToken;

        $payload = [
            'en_name' => 'Pandit Dev Sharma',
            'en_expertise' => 'Vedic Astrology, Kundli',
            'en_languages' => 'English, Hindi',
            'en_excerpt' => 'Dedicated Vedic astrologer.',
            'en_description' => 'Detailed bio in English.',
            'hi_name' => 'पंडित देव शर्मा',
            'hi_expertise' => 'वैदिक ज्योतिष, कुंडली',
            'hi_languages' => 'हिंदी, अंग्रेजी',
            'hi_excerpt' => 'समर्पित वैदिक ज्योतिषी।',
            'hi_description' => 'विस्तृत हिंदी विवरण।',
            'experience' => '8 years',
            'price' => 15,
            'call_price' => 20,
            'original_price' => 30,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/app-listing', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('astrologer_applications', [
            'admin_id' => $this->astrologerAdmin->id,
            'status' => 'pending',
            'en_name' => 'Pandit Dev Sharma',
            'hi_name' => 'पंडित देव शर्मा',
        ]);

        // CRITICAL CHECK: published astrologers table must NOT have records yet!
        $this->assertDatabaseMissing('astrologers', [
            'admin_id' => $this->astrologerAdmin->id,
        ]);
    }

    public function test_active_pandit_setu_public_profile_does_not_auto_publish_to_astro_setu(): void
    {
        // Another astrologer who is approved
        Astrologer::create([
            'admin_id' => $this->secondAstrologerAdmin->id,
            'name' => 'Acharya Mohan',
            'language' => 'en',
            'astrologer_language' => 'English, Hindi',
            'expertise' => 'Vedic Astrology',
            'experience' => '5 years',
            'active' => 1,
            'status' => 'online',
        ]);

        // Create an active public profile in Pandit Setu for Pandit Dev
        ProfessionalPublicProfile::create([
            'admin_id' => $this->astrologerAdmin->id,
            'slug' => 'pandit-dev',
            'display_name' => 'Pandit Dev Live Profile',
            'is_published' => true,
            'profile_status' => 'published',
        ]);

        // Astro Setu consumer API check
        $consumerResponseEn = $this->getJson('/api/en/astrologers');
        $consumerResponseEn->assertStatus(200);

        // Pandit Dev must NOT appear in consumer astrologers listing
        $consumerData = $consumerResponseEn->json();
        $found = collect($consumerData)->contains(function ($item) {
            return ($item['astrologer']['admin_id'] ?? null) === $this->astrologerAdmin->id;
        });

        $this->assertFalse($found, 'Active Pandit Setu public profile should never automatically appear in Astro Setu consumer listings.');
    }

    public function test_admin_approval_creates_both_en_and_hi_records_and_is_idempotent(): void
    {
        // 1. Submit application
        $app = AstrologerApplication::create([
            'admin_id' => $this->astrologerAdmin->id,
            'status' => 'pending',
            'experience' => '10 years',
            'price' => 25,
            'call_price' => 30,
            'original_price' => 50,
            'en_name' => 'Pandit Dev Sharma',
            'en_expertise' => 'Vedic Astrology',
            'en_languages' => 'English, Hindi',
            'en_excerpt' => 'Vedic expert excerpt',
            'en_description' => 'Full english bio',
            'hi_name' => 'पंडित देव शर्मा',
            'hi_expertise' => 'वैदिक ज्योतिष',
            'hi_languages' => 'हिंदी, अंग्रेजी',
            'hi_excerpt' => 'वैदिक विशेषज्ञ सारांश',
            'hi_description' => 'पूर्ण हिंदी विवरण',
            'submitted_at' => now(),
        ]);

        // 2. Admin approves via controller
        $this->actingAs($this->superAdmin, 'admin');

        $approveResponse = $this->post(route('admin.astrologer-applications.approve', $app->id));
        $approveResponse->assertSessionHas('success');

        $app->refresh();
        $this->assertEquals('approved', $app->status);
        $this->assertEquals($this->superAdmin->id, $app->reviewed_by);
        $this->assertNotNull($app->reviewed_at);

        // 3. Verify exactly 2 records created in published astrologers table
        $published = Astrologer::where('admin_id', $this->astrologerAdmin->id)->get();
        $this->assertCount(2, $published);

        $enRow = $published->where('language', 'en')->first();
        $this->assertNotNull($enRow);
        $this->assertEquals('Pandit Dev Sharma', $enRow->name);
        $this->assertEquals(1, $enRow->active);
        $this->assertEquals(25, $enRow->price);
        $this->assertEquals(30, $enRow->call_price);

        $hiRow = $published->where('language', 'hi')->first();
        $this->assertNotNull($hiRow);
        $this->assertEquals('पंडित देव शर्मा', $hiRow->name);
        $this->assertEquals(1, $hiRow->active);

        // 4. Repeated approval (idempotency check)
        $this->post(route('admin.astrologer-applications.approve', $app->id));
        $publishedAfterSecondApprove = Astrologer::where('admin_id', $this->astrologerAdmin->id)->get();
        $this->assertCount(2, $publishedAfterSecondApprove, 'Repeated approval must not create duplicate records');

        // 5. Verify published listing is now visible in Astro Setu consumer API
        $consumerEn = $this->getJson('/api/en/astrologers');
        $consumerEn->assertStatus(200);
        $foundEn = collect($consumerEn->json())->contains(function ($item) {
            return ($item['astrologer']['admin_id'] ?? null) === $this->astrologerAdmin->id
                && $item['astrologer']['name'] === 'Pandit Dev Sharma';
        });
        $this->assertTrue($foundEn, 'Approved English listing must be visible in consumer API');

        $consumerHi = $this->getJson('/api/hi/astrologers');
        $consumerHi->assertStatus(200);
        $foundHi = collect($consumerHi->json())->contains(function ($item) {
            return ($item['astrologer']['admin_id'] ?? null) === $this->astrologerAdmin->id
                && $item['astrologer']['name'] === 'पंडित देव शर्मा';
        });
        $this->assertTrue($foundHi, 'Approved Hindi listing must be visible in consumer API');
    }

    public function test_admin_rejection_marks_application_rejected_and_deactivates_listings(): void
    {
        // Create an existing application
        $app = AstrologerApplication::create([
            'admin_id' => $this->astrologerAdmin->id,
            'status' => 'pending',
            'experience' => '10 years',
            'price' => 25,
            'call_price' => 30,
            'en_name' => 'Pandit Dev',
            'en_expertise' => 'Vedic Astrology',
            'en_languages' => 'English',
            'hi_name' => 'पंडित देव',
            'hi_expertise' => 'वैदिक ज्योतिष',
            'hi_languages' => 'हिंदी',
        ]);

        $this->actingAs($this->superAdmin, 'admin');

        $rejectResponse = $this->post(route('admin.astrologer-applications.reject', $app->id), [
            'rejection_reason' => 'Profile image is missing clear practitioner headshot.',
        ]);

        $rejectResponse->assertSessionHas('success');

        $app->refresh();
        $this->assertEquals('rejected', $app->status);
        $this->assertEquals('Profile image is missing clear practitioner headshot.', $app->rejection_reason);
        $this->assertEquals($this->superAdmin->id, $app->reviewed_by);

        // Published records must be inactive or missing
        $activePublished = Astrologer::where('admin_id', $this->astrologerAdmin->id)->where('active', 1)->get();
        $this->assertCount(0, $activePublished);
    }

    public function test_practitioner_cannot_tamper_with_another_practitioners_application(): void
    {
        // Dev submits an application
        AstrologerApplication::create([
            'admin_id' => $this->astrologerAdmin->id,
            'status' => 'pending',
            'experience' => '5 years',
            'price' => 10,
            'call_price' => 10,
            'en_name' => 'Dev',
            'en_expertise' => 'Astrology',
            'en_languages' => 'English',
            'hi_name' => 'देव',
            'hi_expertise' => 'ज्योतिष',
            'hi_languages' => 'हिंदी',
        ]);

        // Mohan logs in
        $mohanToken = $this->secondAstrologerAdmin->createToken('mohan-token')->plainTextToken;

        // Mohan queries /api/astrologer/app-listing - should only see Mohan's status (not Dev's!)
        $response = $this->withHeader('Authorization', 'Bearer ' . $mohanToken)
            ->getJson('/api/astrologer/app-listing');

        $response->assertStatus(200)
            ->assertJson([
                'has_application' => false,
                'status' => 'not_applied',
            ]);

        // Mohan submits with his token
        $submitResponse = $this->withHeader('Authorization', 'Bearer ' . $mohanToken)
            ->postJson('/api/astrologer/app-listing', [
                'en_name' => 'Mohan Astro',
                'en_expertise' => 'Numerology',
                'en_languages' => 'English',
                'hi_name' => 'मोहन एस्ट्रो',
                'hi_expertise' => 'अंकशास्त्र',
                'hi_languages' => 'हिंदी',
                'experience' => '3 years',
                'price' => 12,
                'call_price' => 15,
            ]);

        $submitResponse->assertStatus(201);

        // Verify Mohan's application was created with admin_id = Mohan's id (not Dev's id)
        $this->assertDatabaseHas('astrologer_applications', [
            'admin_id' => $this->secondAstrologerAdmin->id,
            'en_name' => 'Mohan Astro',
        ]);
    }

    public function test_practitioner_can_upload_image_file_and_get_public_url(): void
    {
        Storage::fake('public');
        $token = $this->astrologerAdmin->createToken('token')->plainTextToken;

        $file = UploadedFile::fake()->image('profile.jpg', 600, 600);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/app-listing', [
                'en_name' => 'Pandit Dev',
                'en_expertise' => 'Vedic Astrology',
                'en_languages' => 'English, Hindi',
                'hi_name' => 'पंडित देव',
                'hi_expertise' => 'वैदिक ज्योतिष',
                'hi_languages' => 'हिन्दी, अंग्रेजी',
                'experience' => '8 years',
                'price' => 20,
                'call_price' => 25,
                'image' => $file,
            ]);

        $response->assertStatus(201);
        $savedPath = $response->json('application.image');

        $this->assertNotNull($savedPath);
        $this->assertStringStartsWith('astro-setu/applications/', $savedPath);
        Storage::disk('public')->assertExists($savedPath);

        // Verify image_url is appended and is accessible
        $imageUrl = $response->json('application.image_url');
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString($savedPath, $imageUrl);
    }

    public function test_image_upload_validation_rejects_invalid_file_type_and_large_files(): void
    {
        Storage::fake('public');
        $token = $this->astrologerAdmin->createToken('token')->plainTextToken;

        // 1. Reject invalid file type (PDF)
        $pdfFile = UploadedFile::fake()->create('document.pdf', 200, 'application/pdf');
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/app-listing', [
                'en_name' => 'Pandit Dev',
                'en_expertise' => 'Vedic Astrology',
                'en_languages' => 'English, Hindi',
                'hi_name' => 'पंडित देव',
                'hi_expertise' => 'वैदिक ज्योतिष',
                'hi_languages' => 'हिन्दी, अंग्रेजी',
                'experience' => '8 years',
                'price' => 20,
                'call_price' => 25,
                'image' => $pdfFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // 2. Reject file larger than 5MB
        $largeFile = UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg');
        $responseLarge = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/app-listing', [
                'en_name' => 'Pandit Dev',
                'en_expertise' => 'Vedic Astrology',
                'en_languages' => 'English, Hindi',
                'hi_name' => 'पंडित देव',
                'hi_expertise' => 'वैदिक ज्योतिष',
                'hi_languages' => 'हिन्दी, अंग्रेजी',
                'experience' => '8 years',
                'price' => 20,
                'call_price' => 25,
                'image' => $largeFile,
            ]);

        $responseLarge->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_practitioner_can_edit_pending_application_preserving_id_and_submitted_at(): void
    {
        Storage::fake('public');
        $token = $this->astrologerAdmin->createToken('token')->plainTextToken;

        $submittedTime = now()->subHours(2);
        $app = AstrologerApplication::create([
            'admin_id' => $this->astrologerAdmin->id,
            'status' => 'pending',
            'experience' => '5 years',
            'price' => 15,
            'call_price' => 20,
            'original_price' => 25,
            'en_name' => 'Original Name',
            'en_expertise' => 'Original Expertise',
            'en_languages' => 'English',
            'hi_name' => 'मूल नाम',
            'hi_expertise' => 'मूल विद्या',
            'hi_languages' => 'हिंदी',
            'submitted_at' => $submittedTime,
        ]);

        $originalId = $app->id;

        // Perform edit via PUT /api/astrologer/app-listing
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/astrologer/app-listing', [
                'en_name' => 'Updated Pandit Dev',
                'en_expertise' => 'Vedic, Palmistry',
                'en_languages' => 'English, Hindi, Sanskrit',
                'en_excerpt' => 'New updated excerpt',
                'en_description' => 'New updated bio',
                'hi_name' => 'अपडेटेड पंडित देव',
                'hi_expertise' => 'वैदिक, हस्तरेखा',
                'hi_languages' => 'हिंदी, संस्कृत',
                'hi_excerpt' => 'नया संक्षिप्त विवरण',
                'hi_description' => 'नया विस्तृत विवरण',
                'experience' => '9 years',
                'price' => 30,
                'call_price' => 35,
                'original_price' => 45,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'pending',
                'application' => [
                    'id' => $originalId,
                    'en_name' => 'Updated Pandit Dev',
                    'hi_name' => 'अपडेटेड पंडित देव',
                    'price' => 30,
                    'call_price' => 35,
                ],
            ]);

        // Verify DB record
        $app->refresh();
        $this->assertEquals($originalId, $app->id);
        $this->assertEquals('pending', $app->status);
        $this->assertEquals('Updated Pandit Dev', $app->en_name);
        $this->assertEquals('अपडेटेड पंडित देव', $app->hi_name);
        $this->assertEquals(30, $app->price);
        $this->assertEquals(35, $app->call_price);
        $this->assertEquals($submittedTime->toDateTimeString(), $app->submitted_at->toDateTimeString());

        // Verify count of applications is still 1 (no duplicate record)
        $this->assertEquals(1, AstrologerApplication::where('admin_id', $this->astrologerAdmin->id)->count());

        // Verify astrologers table has not been touched or published
        $this->assertEquals(0, Astrologer::where('admin_id', $this->astrologerAdmin->id)->count());
    }

    public function test_editing_non_pending_application_is_rejected(): void
    {
        $token = $this->astrologerAdmin->createToken('token')->plainTextToken;

        // Approved application
        $app = AstrologerApplication::create([
            'admin_id' => $this->astrologerAdmin->id,
            'status' => 'approved',
            'experience' => '5 years',
            'price' => 15,
            'call_price' => 20,
            'en_name' => 'Approved Practitioner',
            'en_expertise' => 'Vedic',
            'en_languages' => 'English',
            'hi_name' => 'स्वीकृत ज्योतिषी',
            'hi_expertise' => 'वैदिक',
            'hi_languages' => 'हिंदी',
            'submitted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/astrologer/app-listing', [
                'en_name' => 'Attempted Edit',
                'en_expertise' => 'Vedic',
                'en_languages' => 'English',
                'hi_name' => 'प्रयास',
                'hi_expertise' => 'वैदिक',
                'hi_languages' => 'हिंदी',
                'experience' => '5 years',
                'price' => 15,
                'call_price' => 20,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_safe_image_replacement_cleans_up_old_image_only_when_not_shared(): void
    {
        Storage::fake('public');
        $token = $this->astrologerAdmin->createToken('token')->plainTextToken;

        // 1. Submit initial image
        $file1 = UploadedFile::fake()->image('image1.jpg');
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/app-listing', [
                'en_name' => 'Dev',
                'en_expertise' => 'Astrology',
                'en_languages' => 'English',
                'hi_name' => 'देव',
                'hi_expertise' => 'ज्योतिष',
                'hi_languages' => 'हिंदी',
                'experience' => '5 years',
                'price' => 10,
                'call_price' => 10,
                'image' => $file1,
            ]);
        $res1->assertStatus(201);
        $path1 = $res1->json('application.image');
        Storage::disk('public')->assertExists($path1);

        // 2. Replace with image 2 on pending update - since image1 is unshared, it should be deleted
        $file2 = UploadedFile::fake()->image('image2.jpg');
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/astrologer/app-listing', [
                'en_name' => 'Dev',
                'en_expertise' => 'Astrology',
                'en_languages' => 'English',
                'hi_name' => 'देव',
                'hi_expertise' => 'ज्योतिष',
                'hi_languages' => 'हिंदी',
                'experience' => '5 years',
                'price' => 10,
                'call_price' => 10,
                'image' => $file2,
            ]);
        $res2->assertStatus(200);
        $path2 = $res2->json('application.image');
        Storage::disk('public')->assertExists($path2);
        Storage::disk('public')->assertMissing($path1);

        // 3. Now make image2 shared with an active published astrologer row
        Astrologer::create([
            'admin_id' => $this->astrologerAdmin->id,
            'name' => 'Dev Published',
            'language' => 'en',
            'astrologer_language' => 'English',
            'image' => $path2,
            'expertise' => 'Astrology',
            'experience' => '5 years',
            'price' => 10,
            'call_price' => 10,
            'active' => 1,
            'status' => 'online',
        ]);

        // 4. Replace with image 3 - image2 is SHARED, so image2 must NOT be deleted!
        $file3 = UploadedFile::fake()->image('image3.jpg');
        $res3 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/astrologer/app-listing', [
                'en_name' => 'Dev',
                'en_expertise' => 'Astrology',
                'en_languages' => 'English',
                'hi_name' => 'देव',
                'hi_expertise' => 'ज्योतिष',
                'hi_languages' => 'हिंदी',
                'experience' => '5 years',
                'price' => 10,
                'call_price' => 10,
                'image' => $file3,
            ]);
        $res3->assertStatus(200);
        $path3 = $res3->json('application.image');
        Storage::disk('public')->assertExists($path3);
        // Assert image2 was PRESERVED because it is used by a published astrologer
        Storage::disk('public')->assertExists($path2);
    }

    public function test_admin_sees_updated_pending_data_and_approves_latest_version(): void
    {
        Storage::fake('public');
        $token = $this->astrologerAdmin->createToken('token')->plainTextToken;

        $file = UploadedFile::fake()->image('final_photo.jpg');

        // Submit initial application
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/app-listing', [
                'en_name' => 'Dev V1',
                'en_expertise' => 'V1 Expertise',
                'en_languages' => 'English',
                'hi_name' => 'देव V1',
                'hi_expertise' => 'V1 विद्या',
                'hi_languages' => 'हिंदी',
                'experience' => '2 years',
                'price' => 10,
                'call_price' => 12,
            ]);

        // Edit pending application with updated profile and image
        $editRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/astrologer/app-listing', [
                'en_name' => 'Dev V2 Final',
                'en_expertise' => 'V2 Advanced Expertise',
                'en_languages' => 'English, Sanskrit',
                'hi_name' => 'देव V2 फाइनल',
                'hi_expertise' => 'V2 उन्नत विद्या',
                'hi_languages' => 'हिंदी, संस्कृत',
                'experience' => '7 years',
                'price' => 25,
                'call_price' => 30,
                'image' => $file,
            ]);

        $editRes->assertStatus(200);
        $appId = $editRes->json('application.id');
        $appImage = $editRes->json('application.image');

        // Admin logs in and views the show page
        $adminView = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.astrologer-applications.show', $appId));

        $adminView->assertStatus(200)
            ->assertSee('Dev V2 Final')
            ->assertSee('V2 Advanced Expertise')
            ->assertSee('देव V2 फाइनल')
            ->assertSee('V2 उन्नत विद्या')
            ->assertSee('7 years');

        // Admin approves application
        $approveResponse = $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.astrologer-applications.approve', $appId));

        $approveResponse->assertRedirect(route('admin.astrologer-applications.show', $appId));

        // Verify published listings reflect latest V2 data
        $this->assertDatabaseHas('astrologers', [
            'admin_id' => $this->astrologerAdmin->id,
            'language' => 'en',
            'name' => 'Dev V2 Final',
            'expertise' => 'V2 Advanced Expertise',
            'image' => $appImage,
            'price' => 25,
            'call_price' => 30,
            'active' => 1,
        ]);

        $this->assertDatabaseHas('astrologers', [
            'admin_id' => $this->astrologerAdmin->id,
            'language' => 'hi',
            'name' => 'देव V2 फाइनल',
            'expertise' => 'V2 उन्नत विद्या',
            'image' => $appImage,
            'price' => 25,
            'call_price' => 30,
            'active' => 1,
        ]);
    }
}
