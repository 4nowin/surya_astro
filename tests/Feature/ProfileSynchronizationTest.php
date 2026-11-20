<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use App\Models\ProfessionalPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $practitioner;
    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'admin']);
        $astroRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->superAdmin = Admin::create([
            'name' => 'Platform Super Admin',
            'email' => 'admin@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->superAdmin->assignRole($adminRole);

        $this->practitioner = Admin::create([
            'name' => 'Acharya Dev',
            'email' => 'acharya.dev@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->practitioner->assignRole($astroRole);
    }

    public function test_public_profile_update_synchronizes_to_published_astrologers_records(): void
    {
        $token = $this->practitioner->createToken('token')->plainTextToken;

        // 1. Existing published listing in Astro Setu
        $enAstro = Astrologer::create([
            'admin_id' => $this->practitioner->id,
            'name' => 'Acharya Dev Old',
            'language' => 'en',
            'astrologer_language' => 'English',
            'expertise' => 'Vedic Astrology',
            'experience' => '5 years',
            'excerpt' => 'Old excerpt',
            'description' => 'Old description',
            'image' => 'old_photo.jpg',
            'price' => 15,
            'active' => 1,
            'status' => 'online',
        ]);

        $hiAstro = Astrologer::create([
            'admin_id' => $this->practitioner->id,
            'name' => 'आचार्य देव (हिंदी)',
            'language' => 'hi',
            'astrologer_language' => 'हिंदी',
            'expertise' => 'वैदिक ज्योतिष',
            'experience' => '5 years',
            'excerpt' => 'पुराना सारांश',
            'description' => 'पुराना विवरण',
            'image' => 'old_photo.jpg',
            'price' => 15,
            'active' => 1,
            'status' => 'online',
        ]);

        // 2. Practitioner updates public profile in Pandit Setu
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/public-profile', [
                'display_name' => 'Acharya Dev Sharma',
                'headline' => 'Renowned Master of Shukla Yajurveda & Jyotish',
                'about' => 'Practicing authentic Vedic astrology for over 12 years with deep spiritual lineage in Kashi.',
                'years_of_experience' => 12,
                'languages' => ['Hindi', 'English', 'Sanskrit'],
                'specializations' => ['Vedic Astrology', 'Kundli', 'Vastu Shastra'],
                'profile_photo_url' => 'https://storage.astrosetu.com/profiles/acharya_dev_new.jpg',
            ]);

        $response->assertStatus(200);

        // 3. Verify English Astrologer row synchronized
        $enAstro->refresh();
        $this->assertEquals('Acharya Dev Sharma', $enAstro->name);
        $this->assertEquals('Renowned Master of Shukla Yajurveda & Jyotish', $enAstro->excerpt);
        $this->assertEquals('Practicing authentic Vedic astrology for over 12 years with deep spiritual lineage in Kashi.', $enAstro->description);
        $this->assertEquals('12 years', $enAstro->experience);
        $this->assertEquals('Hindi, English, Sanskrit', $enAstro->astrologer_language);
        $this->assertEquals('Vedic Astrology, Kundli, Vastu Shastra', $enAstro->expertise);
        $this->assertEquals('https://storage.astrosetu.com/profiles/acharya_dev_new.jpg', $enAstro->image);

        // 4. Verify Hindi Astrologer row preserved Hindi name and description, but updated experience & image
        $hiAstro->refresh();
        $this->assertEquals('आचार्य देव (हिंदी)', $hiAstro->name, 'Devanagari name must not be overwritten by English display name');
        $this->assertEquals('पुराना विवरण', $hiAstro->description, 'Existing Hindi description must not be clobbered by English text');
        $this->assertEquals('12 years', $hiAstro->experience);
        $this->assertEquals('https://storage.astrosetu.com/profiles/acharya_dev_new.jpg', $hiAstro->image);
    }

    public function test_public_profile_update_does_not_publish_unapproved_practitioner(): void
    {
        $token = $this->practitioner->createToken('token')->plainTextToken;

        // No published records exist
        $this->assertEquals(0, Astrologer::where('admin_id', $this->practitioner->id)->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/astrologer/public-profile', [
                'display_name' => 'Unapproved Pandit',
                'headline' => 'Trying to self-publish',
                'about' => 'Bio text',
                'years_of_experience' => 5,
                'is_published' => true,
            ]);

        $response->assertStatus(200);

        // Must still have 0 published astrologers (requires super admin approval)
        $this->assertEquals(0, Astrologer::where('admin_id', $this->practitioner->id)->count());
    }

    public function test_application_approval_propagates_to_public_profile_harmonizing_types(): void
    {
        $app = AstrologerApplication::create([
            'admin_id' => $this->practitioner->id,
            'status' => 'pending',
            'experience' => '15 years',
            'price' => 20,
            'call_price' => 25,
            'original_price' => 35,
            'en_name' => 'Acharya Dev Approved',
            'en_expertise' => 'Vedic Astrology, Palmistry, Vastu',
            'en_languages' => 'Hindi, English, Gujarati',
            'en_excerpt' => 'Approved excerpt for practitioner',
            'en_description' => 'Approved English bio description',
            'hi_name' => 'आचार्य देव स्वीकृत',
            'hi_expertise' => 'वैदिक ज्योतिष, हस्तरेखा, वास्तु',
            'hi_languages' => 'हिंदी, अंग्रेजी, गुजराती',
            'hi_excerpt' => 'स्वीकृत सारांश',
            'hi_description' => 'स्वीकृत हिंदी विवरण',
            'image' => 'astro-setu/applications/approved_face.jpg',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->superAdmin, 'admin');
        $approveResponse = $this->post(route('admin.astrologer-applications.approve', $app->id));
        $approveResponse->assertSessionHas('success');

        // Check ProfessionalPublicProfile was updated and types harmonized
        $profile = ProfessionalPublicProfile::where('admin_id', $this->practitioner->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals('Acharya Dev Approved', $profile->display_name);
        $this->assertEquals('Approved excerpt for practitioner', $profile->headline);
        $this->assertEquals('Approved English bio description', $profile->about);
        $this->assertEquals(15, $profile->years_of_experience, 'String "15 years" must be harmonized to integer 15');
        $this->assertEquals(['Hindi', 'English', 'Gujarati'], $profile->languages, 'Comma string must be harmonized to array');
        $this->assertEquals(['Vedic Astrology', 'Palmistry', 'Vastu'], $profile->specializations, 'Expertise string must be harmonized to array');
        $this->assertEquals('astro-setu/applications/approved_face.jpg', $profile->profile_photo_url);
    }

    public function test_status_prefill_prefers_newer_public_profile_over_stale_processed_application(): void
    {
        $token = $this->practitioner->createToken('token')->plainTextToken;

        // Old application from 10 days ago
        AstrologerApplication::create([
            'admin_id' => $this->practitioner->id,
            'status' => 'approved',
            'experience' => '5 years',
            'price' => 10,
            'call_price' => 15,
            'en_name' => 'Old Name From App',
            'en_expertise' => 'Old Astrology',
            'en_languages' => 'English',
            'en_excerpt' => 'Old excerpt',
            'en_description' => 'Old bio',
            'hi_name' => 'पुराना नाम',
            'hi_expertise' => 'पुराना ज्ञान',
            'hi_languages' => 'हिंदी',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
            'submitted_at' => now()->subDays(10),
        ]);

        // Practitioner recently edited public profile 1 hour ago
        ProfessionalPublicProfile::create([
            'admin_id' => $this->practitioner->id,
            'display_name' => 'Fresh Profile Name',
            'headline' => 'Fresh Headline',
            'about' => 'Fresh updated about text',
            'years_of_experience' => 8,
            'languages' => ['Hindi', 'English', 'Bengali'],
            'specializations' => ['Vedic Astrology', 'Nadi Astrology'],
            'profile_photo_url' => 'https://example.com/fresh_photo.jpg',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/astrologer/app-listing');

        $response->assertStatus(200);
        $prefill = $response->json('prefill');

        $this->assertEquals('Fresh Profile Name', $prefill['en_name']);
        $this->assertEquals('Fresh Headline', $prefill['en_excerpt']);
        $this->assertEquals('Fresh updated about text', $prefill['en_description']);
        $this->assertEquals('8 years', $prefill['experience']);
        $this->assertEquals('Hindi, English, Bengali', $prefill['en_languages']);
        $this->assertEquals('Vedic Astrology, Nadi Astrology', $prefill['en_expertise']);
        $this->assertEquals('https://example.com/fresh_photo.jpg', $prefill['image']);
    }

    public function test_astrologer_model_accessors_gracefully_fallback_to_public_profile(): void
    {
        // Create Public Profile
        ProfessionalPublicProfile::create([
            'admin_id' => $this->practitioner->id,
            'display_name' => 'Fallback Master Dev',
            'headline' => 'Fallback Master Headline',
            'about' => 'Fallback About Description',
            'years_of_experience' => 11,
            'languages' => ['Hindi', 'English'],
            'specializations' => ['Vedic Astrology', 'Prashna'],
            'profile_photo_url' => 'https://example.com/dev_fallback.jpg',
        ]);

        // Create published Astrologer with empty string/null fields
        $astrologer = Astrologer::create([
            'admin_id' => $this->practitioner->id,
            'language' => 'en',
            'name' => '',
            'image' => '',
            'excerpt' => '',
            'description' => '',
            'experience' => '',
            'astrologer_language' => '',
            'expertise' => '',
            'price' => 15,
            'active' => 1,
            'status' => 'online',
        ]);

        // Eager load admin.publicProfile as API controllers do
        $astrologer->load('admin.publicProfile');

        $this->assertEquals('Fallback Master Dev', $astrologer->name);
        $this->assertEquals('https://example.com/dev_fallback.jpg', $astrologer->image);
        $this->assertEquals('https://example.com/dev_fallback.jpg', $astrologer->image_url);
        $this->assertEquals('Fallback Master Headline', $astrologer->excerpt);
        $this->assertEquals('Fallback About Description', $astrologer->description);
        $this->assertEquals('11 years', $astrologer->experience);
        $this->assertEquals('Hindi, English', $astrologer->astrologer_language);
        $this->assertEquals('Vedic Astrology, Prashna', $astrologer->expertise);
    }
}
