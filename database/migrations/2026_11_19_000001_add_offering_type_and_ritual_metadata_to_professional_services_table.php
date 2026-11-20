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
        if (Schema::hasTable('professional_services')) {
            Schema::table('professional_services', function (Blueprint $table) {
                // 1. Dedicated offering type: 'chat', 'product', 'puja'
                if (!Schema::hasColumn('professional_services', 'offering_type')) {
                    $table->string('offering_type', 32)->nullable()->default('product')->after('category')->index();
                }

                // 2. Puja & Ceremony duration attributes
                if (!Schema::hasColumn('professional_services', 'duration_value')) {
                    $table->unsignedInteger('duration_value')->nullable()->after('duration_minutes');
                }
                if (!Schema::hasColumn('professional_services', 'duration_unit')) {
                    $table->string('duration_unit', 16)->default('minutes')->after('duration_value');
                }

                // 3. Remote fulfillment flags for Pujas & Ceremonies
                if (!Schema::hasColumn('professional_services', 'has_video_proof')) {
                    $table->boolean('has_video_proof')->default(false)->after('max_message_characters');
                }
                if (!Schema::hasColumn('professional_services', 'has_prasad_delivery')) {
                    $table->boolean('has_prasad_delivery')->default(false)->after('has_video_proof');
                }
                if (!Schema::hasColumn('professional_services', 'attendance_mode')) {
                    $table->string('attendance_mode', 32)->default('both')->after('has_prasad_delivery');
                }
            });

            // 4. Backfill existing records safely
            // a. Chat services
            DB::table('professional_services')
                ->where(function ($query) {
                    $query->where('pricing_model', 'per_message')
                          ->orWhere('name', 'like', '%chat%')
                          ->orWhere('delivery_methods', 'like', '%"chat"%');
                })
                ->update(['offering_type' => 'chat']);

            // b. Puja / Ritual services
            DB::table('professional_services')
                ->where('offering_type', '!=', 'chat')
                ->where(function ($query) {
                    $query->where('category', 'pandit_purohit')
                          ->orWhere('name', 'like', '%puja%')
                          ->orWhere('name', 'like', '%pooja%')
                          ->orWhere('name', 'like', '%katha%')
                          ->orWhere('name', 'like', '%abhishek%')
                          ->orWhere('name', 'like', '%hawan%')
                          ->orWhere('name', 'like', '%shanti%')
                          ->orWhere('name', 'like', '%anusthan%')
                          ->orWhere('name', 'like', '%sanskar%')
                          ->orWhere('name', 'like', '%ceremony%');
                })
                ->update([
                    'offering_type' => 'puja',
                    'duration_unit' => 'minutes',
                ]);

            // Copy duration_minutes to duration_value for pujas where duration_minutes > 0
            DB::statement('UPDATE professional_services SET duration_value = duration_minutes WHERE offering_type = "puja" AND duration_minutes > 0 AND duration_value IS NULL');

            // c. Remaining offerings default to 'product'
            DB::table('professional_services')
                ->whereNull('offering_type')
                ->update(['offering_type' => 'product']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('professional_services')) {
            Schema::table('professional_services', function (Blueprint $table) {
                $columns = [
                    'offering_type',
                    'duration_value',
                    'duration_unit',
                    'has_video_proof',
                    'has_prasad_delivery',
                    'attendance_mode',
                ];
                $existing = array_filter($columns, fn ($col) => Schema::hasColumn('professional_services', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
