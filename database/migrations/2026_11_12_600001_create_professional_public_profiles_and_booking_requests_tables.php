<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Professional Public Profiles
        Schema::create('professional_public_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->unique();
            $table->string('slug', 100)->unique()->index();
            $table->string('display_name', 191);
            $table->string('profile_photo_url', 500)->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->string('headline', 255)->nullable();
            $table->text('about')->nullable();
            $table->json('categories')->nullable();
            $table->json('specializations')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedTinyInteger('years_of_experience')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('service_area', 255)->nullable();
            $table->boolean('is_online_available')->default(true);
            $table->boolean('is_in_person_available')->default(false);
            $table->boolean('is_home_visit_available')->default(false);
            $table->string('public_phone', 32)->nullable();
            $table->string('public_email', 191)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('public_address')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('is_accepting_bookings')->default(true);
            $table->boolean('is_email_mandatory')->default(false);
            $table->string('profile_status', 32)->default('not_published'); // not_published, published, temporarily_unavailable
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        // 2. Extend Professional Services with Public Visibility settings
        Schema::table('professional_services', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('is_active')->index();
            $table->text('public_description')->nullable()->after('is_public');
        });

        // 3. Client Appointment Booking Requests
        Schema::create('professional_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('booking_reference', 64)->unique()->index();
            $table->string('access_token', 64)->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('service_name_snapshot', 191);
            $table->decimal('agreed_price_snapshot', 10, 2)->nullable();
            $table->string('currency_snapshot', 8)->default('INR');
            $table->unsignedInteger('duration_minutes_snapshot')->default(30);
            $table->string('delivery_method', 50)->default('online'); // online, in_person, home_visit, phone_call
            $table->dateTime('requested_start_at'); // UTC
            $table->dateTime('requested_end_at');   // UTC
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('client_name', 191);
            $table->string('client_phone', 32);
            $table->string('client_email', 191)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('client_message')->nullable();
            $table->string('status', 32)->default('pending')->index(); // pending, confirmed, rejected, alternative_proposed, cancelled, expired
            $table->text('rejection_reason')->nullable();
            $table->dateTime('alternative_proposed_start_at')->nullable();
            $table->dateTime('alternative_proposed_end_at')->nullable();
            $table->text('alternative_notes')->nullable();
            $table->unsignedBigInteger('jajman_id')->nullable()->index();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('professional_services')->nullOnDelete();
            $table->foreign('jajman_id')->references('id')->on('jajmans')->nullOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();

            $table->index(['admin_id', 'status', 'created_at'], 'idx_admin_booking_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_booking_requests');

        Schema::table('professional_services', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn(['is_public', 'public_description']);
        });

        Schema::dropIfExists('professional_public_profiles');
    }
};
