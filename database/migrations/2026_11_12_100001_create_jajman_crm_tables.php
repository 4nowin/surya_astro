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
        // 1. Independent Jajmans Directory Table
        Schema::create('jajmans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 25)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('gender', 20)->nullable(); // male, female, other
            $table->date('date_of_birth')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // for future verified Astro Setu link
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Professional-Jajman Relationship Pivot
        Schema::create('professional_jajmans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index(); // The professional (admins table)
            $table->unsignedBigInteger('jajman_id')->index();
            $table->string('status', 30)->default('active'); // active, archived, lead
            $table->string('source', 50)->default('manual'); // manual, linked, referral
            $table->timestamps();

            $table->unique(['admin_id', 'jajman_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
            $table->foreign('jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
        });

        // 3. Jajman Birth Details
        Schema::create('jajman_birth_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jajman_id')->index();
            $table->date('date_of_birth');
            $table->time('birth_time')->nullable();
            $table->boolean('time_unknown')->default(false);
            $table->string('birth_place');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->foreign('jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
        });

        // 4. Jajman Family Relationships
        Schema::create('jajman_family_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index(); // Scoped to professional relationship
            $table->unsignedBigInteger('jajman_id')->index();
            $table->unsignedBigInteger('related_jajman_id')->index();
            $table->string('relationship_type', 50); // father, mother, spouse, son, daughter, brother, sister, other
            $table->timestamps();

            $table->unique(['admin_id', 'jajman_id', 'related_jajman_id'], 'prof_jajman_rel_unique');
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
            $table->foreign('jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
            $table->foreign('related_jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
        });

        // 5. Professional Private Notes (Strictly private to the professional)
        Schema::create('jajman_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index(); // Owner professional
            $table->unsignedBigInteger('jajman_id')->index();
            $table->text('content');
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
            $table->foreign('jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jajman_notes');
        Schema::dropIfExists('jajman_family_relationships');
        Schema::dropIfExists('jajman_birth_details');
        Schema::dropIfExists('professional_jajmans');
        Schema::dropIfExists('jajmans');
    }
};
