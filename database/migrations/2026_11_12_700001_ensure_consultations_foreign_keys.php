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
                // If information_schema lookup fails, attempt safe check
            }

            if (!$hasFk) {
                // Clean orphan jajman_id values before attaching foreign key
                DB::statement("UPDATE consultations SET jajman_id = NULL WHERE jajman_id IS NOT NULL AND jajman_id NOT IN (SELECT id FROM jajmans)");

                Schema::table('consultations', function (Blueprint $table) {
                    $table->foreign('jajman_id')->references('id')->on('jajmans')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('consultations')) {
            try {
                Schema::table('consultations', function (Blueprint $table) {
                    $table->dropForeign(['jajman_id']);
                });
            } catch (\Throwable $e) {
                // Ignore if constraint does not exist
            }
        }
    }
};
