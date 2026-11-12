<?php

declare(strict_types=1);

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
        // 1. Create dedicated persistent daily Panchang cache table in MySQL
        if (!Schema::hasTable('daily_panchang_cache')) {
            Schema::create('daily_panchang_cache', function (Blueprint $table) {
                $table->id();
                $table->string('canonical_key', 64)->unique();
                $table->date('date')->index();
                $table->decimal('latitude', 8, 4);
                $table->decimal('longitude', 8, 4);
                $table->string('timezone', 64);
                $table->decimal('elevation', 8, 2)->default(0.00);
                $table->longText('result');
                $table->timestamps();

                $table->index(['date', 'latitude', 'longitude'], 'daily_panchang_date_lat_lon_idx');
            });
        }

        // 2. Add result column to astrology_calculation_jobs for persistent MySQL storage across all calculation types
        if (Schema::hasTable('astrology_calculation_jobs') && !Schema::hasColumn('astrology_calculation_jobs', 'result')) {
            Schema::table('astrology_calculation_jobs', function (Blueprint $table) {
                $table->longText('result')->nullable()->after('result_cache_key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('astrology_calculation_jobs') && Schema::hasColumn('astrology_calculation_jobs', 'result')) {
            Schema::table('astrology_calculation_jobs', function (Blueprint $table) {
                $table->dropColumn('result');
            });
        }

        Schema::dropIfExists('daily_panchang_cache');
    }
};
