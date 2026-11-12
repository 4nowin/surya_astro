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
        // 1. Appointments table
        if (!Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->index();
                $table->unsignedBigInteger('jajman_id')->nullable()->index();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('appointment_type', 50)->default('online'); // online, in_person, phone_call, puja, vastu_visit, other
                $table->string('booking_source', 50)->default('professional_created'); // professional_created, client_requested, astro_setu
                $table->string('status', 30)->default('confirmed'); // pending, confirmed, completed, cancelled, no_show
                $table->dateTime('start_at'); // stored in UTC
                $table->dateTime('end_at');   // stored in UTC
                $table->string('timezone', 64)->default('Asia/Kolkata');
                $table->string('location', 255)->nullable();
                $table->string('meeting_link', 255)->nullable();
                $table->unsignedBigInteger('consultation_id')->nullable()->index();
                $table->text('cancellation_reason')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                if (Schema::hasTable('jajmans')) {
                    $table->foreign('jajman_id')->references('id')->on('jajmans')->nullOnDelete();
                }
                if (Schema::hasTable('consultations')) {
                    $table->foreign('consultation_id')->references('id')->on('consultations')->nullOnDelete();
                }

                $table->index(['admin_id', 'start_at', 'end_at'], 'idx_appts_admin_time');
                $table->index(['admin_id', 'status'], 'idx_appts_admin_status');
            });
        }

        // 2. Weekly Recurring Availability
        if (!Schema::hasTable('professional_availabilities')) {
            Schema::create('professional_availabilities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id');
                $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday, 1 = Monday, ... 6 = Saturday
                $table->boolean('is_enabled')->default(true);
                $table->json('slots')->nullable(); // e.g. [{"start":"09:00","end":"13:00"},{"start":"14:00","end":"18:00"}]
                $table->string('timezone', 64)->default('Asia/Kolkata');
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                $table->unique(['admin_id', 'day_of_week'], 'uniq_admin_day');
            });
        }

        // 3. Date-Specific Availability Exceptions (Leaves, holidays, custom hours)
        if (!Schema::hasTable('professional_availability_exceptions')) {
            Schema::create('professional_availability_exceptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->index();
                $table->date('date'); // YYYY-MM-DD
                $table->string('type', 30)->default('leave'); // leave, holiday, custom_hours
                $table->json('slots')->nullable(); // for custom_hours e.g. [{"start":"10:00","end":"14:00"}]
                $table->string('reason', 191)->nullable();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                $table->index(['admin_id', 'date'], 'idx_admin_date_exception');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_availability_exceptions');
        Schema::dropIfExists('professional_availabilities');
        Schema::dropIfExists('appointments');
    }
};
