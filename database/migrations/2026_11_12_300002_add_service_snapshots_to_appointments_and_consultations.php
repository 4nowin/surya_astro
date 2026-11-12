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
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('jajman_id');
            $table->string('service_name_snapshot', 191)->nullable()->after('title');
            $table->decimal('agreed_price_snapshot', 10, 2)->nullable()->after('service_name_snapshot');
            $table->string('currency_snapshot', 8)->default('INR')->after('agreed_price_snapshot');
            $table->unsignedInteger('duration_minutes_snapshot')->nullable()->after('currency_snapshot');
            $table->string('delivery_method_snapshot', 64)->nullable()->after('duration_minutes_snapshot');

            $table->index('service_id');
        });

        // 2. Add service snapshots to consultations
        Schema::table('consultations', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('jajman_id');
            $table->string('service_name_snapshot', 191)->nullable()->after('service_id');

            $table->index('service_id');
        });
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
