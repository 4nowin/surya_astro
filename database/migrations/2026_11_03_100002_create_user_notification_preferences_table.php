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
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->boolean('master_enabled')->default(true);
            $table->boolean('panchang_enabled')->default(true);
            $table->boolean('horoscope_enabled')->default(true);
            $table->boolean('personalized_enabled')->default(true);
            $table->boolean('reminders_enabled')->default(false);
            $table->unsignedTinyInteger('preferred_delivery_hour')->default(7);
            $table->timestamps();

            $table->index(['timezone', 'preferred_delivery_hour', 'master_enabled'], 'idx_tz_hour_master');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
