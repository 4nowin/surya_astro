<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Independent Jajmans Directory Table
        if (!Schema::hasTable('jajmans')) {
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
        }

        // Attach foreign key to consultations.jajman_id if consultations table exists
        if (Schema::hasTable('consultations') && Schema::hasTable('jajmans')) {
            $hasFk = false;
            try {
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'consultations' 
                      AND CONSTRAINT_NAME = 'consultations_jajman_id_foreign'
                ");
                $hasFk = !empty($constraints);
            } catch (\Throwable $e) {
                // If information_schema lookup is restricted, fallback will be handled
            }

            if (!$hasFk) {
                // Clean any orphan jajman_id before attaching foreign key
                DB::statement("UPDATE consultations SET jajman_id = NULL WHERE jajman_id IS NOT NULL AND jajman_id NOT IN (SELECT id FROM jajmans)");

                Schema::table('consultations', function (Blueprint $table) {
                    $table->foreign('jajman_id')->references('id')->on('jajmans')->nullOnDelete();
                });
            }
        }

        // 2. Professional-Jajman Relationship Pivot
        if (!Schema::hasTable('professional_jajmans')) {
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
        }

        // 3. Jajman Birth Details
        if (!Schema::hasTable('jajman_birth_details')) {
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
        }

        // 4. Jajman Family Relationships
        if (!Schema::hasTable('jajman_family_relationships')) {
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
        }

        // 5. Professional Private Notes (Strictly private to the professional)
        if (!Schema::hasTable('jajman_notes')) {
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
