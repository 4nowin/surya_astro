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
        if (Schema::hasTable('professional_jajmans')) {
            Schema::table('professional_jajmans', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_jajmans', 'has_possible_duplicates')) {
                    $table->boolean('has_possible_duplicates')->default(false)->after('source');
                }
                if (!Schema::hasColumn('professional_jajmans', 'duplicate_candidates')) {
                    $table->json('duplicate_candidates')->nullable()->after('has_possible_duplicates');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('professional_jajmans')) {
            Schema::table('professional_jajmans', function (Blueprint $table) {
                if (Schema::hasColumn('professional_jajmans', 'duplicate_candidates')) {
                    $table->dropColumn('duplicate_candidates');
                }
                if (Schema::hasColumn('professional_jajmans', 'has_possible_duplicates')) {
                    $table->dropColumn('has_possible_duplicates');
                }
            });
        }
    }
};
