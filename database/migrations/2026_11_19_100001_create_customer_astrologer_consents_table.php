<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_astrologer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('status', 32)->default('pending'); // 'granted', 'declined_session', 'withdrawn', 'pending'
            $table->string('scope', 64)->default('personal_birth_profile');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedBigInteger('last_session_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'admin_id']);
            $table->index(['admin_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_astrologer_consents');
    }
};
