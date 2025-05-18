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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('profile_image')->nullable();
            $table->string('name');
            $table->string('language')->default('en');
            $table->index('language');
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->string('email')->unique();
            $table->string('mobile', 15)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string("birth_time")->nullable();
            $table->date("date_of_birth")->nullable();
            $table->string("place_of_birth")->nullable();
            $table->string("country")->nullable();
            $table->enum('role', ['free', 'freemium', 'premium'])->default('free');
            $table->index('role');
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->index('status');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->string("google_id")->nullable();
            $table->string("facebook_id")->nullable();
            $table->string('device_token')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
