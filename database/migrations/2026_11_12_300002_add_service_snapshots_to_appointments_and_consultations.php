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
        // 1. Add service snapshots to appointments
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'service_id')) {
                    $table->unsignedBigInteger('service_id')->nullable()->after('jajman_id');
                    $table->index('service_id');
                }
                if (!Schema::hasColumn('appointments', 'service_name_snapshot')) {
                    $table->string('service_name_snapshot', 191)->nullable()->after('title');
                }
                if (!Schema::hasColumn('appointments', 'agreed_price_snapshot')) {
                    $table->decimal('agreed_price_snapshot', 10, 2)->nullable()->after('service_name_snapshot');
                }
                if (!Schema::hasColumn('appointments', 'currency_snapshot')) {
                    $table->string('currency_snapshot', 8)->default('INR')->after('agreed_price_snapshot');
                }
                if (!Schema::hasColumn('appointments', 'duration_minutes_snapshot')) {
                    $table->unsignedInteger('duration_minutes_snapshot')->nullable()->after('currency_snapshot');
                }
                if (!Schema::hasColumn('appointments', 'delivery_method_snapshot')) {
                    $table->string('delivery_method_snapshot', 64)->nullable()->after('duration_minutes_snapshot');
                }
            });
        }

        // 2. Add service snapshots to consultations
        if (Schema::hasTable('consultations')) {
            Schema::table('consultations', function (Blueprint $table) {
                if (!Schema::hasColumn('consultations', 'service_id')) {
                    $table->unsignedBigInteger('service_id')->nullable()->after('jajman_id');
                    $table->index('service_id');
                }
                if (!Schema::hasColumn('consultations', 'service_name_snapshot')) {
                    $table->string('service_name_snapshot', 191)->nullable()->after('service_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropColumn([
                'service_id',
                'service_name_snapshot',
                'agreed_price_snapshot',
                'currency_snapshot',
                'duration_minutes_snapshot',
                'delivery_method_snapshot',
            ]);
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropColumn([
                'service_id',
                'service_name_snapshot',
            ]);
        });
    }
};
