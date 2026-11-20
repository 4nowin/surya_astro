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
        if (Schema::hasTable('jajmans')) {
            Schema::table('jajmans', function (Blueprint $table) {
                if (!Schema::hasColumn('jajmans', 'merged_into_jajman_id')) {
                    $table->unsignedBigInteger('merged_into_jajman_id')->nullable()->index()->after('user_id');
                    $table->foreign('merged_into_jajman_id')->references('id')->on('jajmans')->nullOnDelete();
                }
                if (!Schema::hasColumn('jajmans', 'merged_at')) {
                    $table->timestamp('merged_at')->nullable()->after('merged_into_jajman_id');
                }
            });
        }

        if (!Schema::hasTable('jajman_merge_logs')) {
            Schema::create('jajman_merge_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->index();
                $table->unsignedBigInteger('primary_jajman_id')->index();
                $table->unsignedBigInteger('secondary_jajman_id')->index();
                $table->json('pre_merge_primary_snapshot')->nullable();
                $table->json('pre_merge_secondary_snapshot')->nullable();
                $table->json('consolidated_counts')->nullable();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                $table->foreign('primary_jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
                $table->foreign('secondary_jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jajman_merge_logs');

        if (Schema::hasTable('jajmans')) {
            Schema::table('jajmans', function (Blueprint $table) {
                if (Schema::hasColumn('jajmans', 'merged_into_jajman_id')) {
                    $table->dropForeign(['merged_into_jajman_id']);
                    $table->dropColumn('merged_into_jajman_id');
                }
                if (Schema::hasColumn('jajmans', 'merged_at')) {
                    $table->dropColumn('merged_at');
                }
            });
        }
    }
};
