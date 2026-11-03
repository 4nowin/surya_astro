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
        Schema::create('saved_kundlis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('profile_id')->nullable()->constrained('user_astrology_profiles')->onDelete('set null');
            $table->string('title')->default('Birth Chart');
            $table->string('person_name');
            $table->string('relationship')->default('self');
            $table->string('canonical_key', 64)->index();
            $table->date('date_of_birth');
            $table->time('birth_time')->nullable();
            $table->string('birth_time_precision', 20)->default('verified');
            $table->string('approximate_time_window', 50)->nullable();
            $table->string('birth_city');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('timezone', 100);
            $table->json('chart_data');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_kundlis');
    }
};
