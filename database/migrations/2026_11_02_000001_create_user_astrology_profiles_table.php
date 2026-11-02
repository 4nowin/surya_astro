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
        Schema::create('user_astrology_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name')->default('Self');
            $table->string('relationship')->default('self');
            $table->boolean('is_default')->default(true);
            $table->date('date_of_birth');
            $table->string('birth_time_precision', 20); // 'verified', 'approximate', 'unknown'
            $table->time('birth_time')->nullable();
            $table->string('approximate_time_window', 50)->nullable(); // e.g. 'morning', 'afternoon', 'evening', 'night'
            $table->string('birth_city');
            $table->string('birth_state')->nullable();
            $table->string('birth_country');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('timezone', 100); // Canonical IANA identifier, e.g. 'Asia/Kolkata'
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index('birth_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_astrology_profiles');
    }
};
