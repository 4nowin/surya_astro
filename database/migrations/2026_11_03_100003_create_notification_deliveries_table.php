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
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_device_id')->nullable()->constrained('user_devices')->onDelete('set null');
            $table->string('notification_type', 50); // daily_panchang, daily_horoscope, personalized_insights, daily_reminder
            $table->date('scheduled_date');
            $table->unsignedTinyInteger('scheduled_hour');
            $table->string('status', 20)->default('pending'); // pending, sent, failed, suppressed
            $table->string('fcm_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_device_id', 'notification_type', 'scheduled_date'], 'unique_device_type_date');
            $table->index(['user_id', 'notification_type', 'scheduled_date'], 'idx_user_type_date');
            $table->index(['scheduled_date', 'status'], 'idx_date_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
