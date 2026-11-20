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
        Schema::table('jajman_kundli_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('jajman_kundli_reports', 'report_category')) {
                $table->string('report_category', 32)->default('kundli')->after('report_type');
            }
            if (!Schema::hasColumn('jajman_kundli_reports', 'tier')) {
                $table->string('tier', 32)->default('free')->after('report_category');
            }
            if (!Schema::hasColumn('jajman_kundli_reports', 'branding_snapshot')) {
                $table->json('branding_snapshot')->nullable()->after('calculation_settings');
            }
            if (!Schema::hasColumn('jajman_kundli_reports', 'meta_payload')) {
                $table->json('meta_payload')->nullable()->after('branding_snapshot');
            }

            $table->index(['admin_id', 'report_category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jajman_kundli_reports', function (Blueprint $table) {
            $table->dropIndex(['admin_id', 'report_category']);
            $table->dropColumn(['report_category', 'tier', 'branding_snapshot', 'meta_payload']);
        });
    }
};
