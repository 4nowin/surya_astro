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
        // 1. Enforce database-level unique constraint on appointments.client_nonce
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                // Drop non-unique index if present and upgrade to unique index
                try {
                    $table->dropIndex('appointments_client_nonce_index');
                } catch (\Throwable $e) {
                    // Index might not exist or already dropped
                }

                if (!Schema::hasColumn('appointments', 'cancellation_review_status')) {
                    $table->string('cancellation_review_status', 32)->default('none')->after('cancellation_reason');
                }
                if (!Schema::hasColumn('appointments', 'resolved_by_admin_id')) {
                    $table->unsignedBigInteger('resolved_by_admin_id')->nullable()->after('no_show_review_status');
                    $table->index('resolved_by_admin_id');
                }
                if (!Schema::hasColumn('appointments', 'resolution_notes')) {
                    $table->text('resolution_notes')->nullable()->after('resolved_by_admin_id');
                }
                if (!Schema::hasColumn('appointments', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable()->after('resolution_notes');
                }
            });

            // Apply unique index on client_nonce
            try {
                Schema::table('appointments', function (Blueprint $table) {
                    $table->unique('client_nonce', 'appointments_client_nonce_unique');
                });
            } catch (\Throwable $e) {
                // If unique already exists
            }
        }

        // 2. Add admin resolution audit columns to professional_service_orders
        if (Schema::hasTable('professional_service_orders')) {
            Schema::table('professional_service_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_service_orders', 'resolved_by_admin_id')) {
                    $table->unsignedBigInteger('resolved_by_admin_id')->nullable()->after('dispute_resolution_notes');
                    $table->index('resolved_by_admin_id');
                }
            });
        }

        // 3. Enforce database-level unique constraint on professional_transactions.reference_id
        if (Schema::hasTable('professional_transactions')) {
            try {
                Schema::table('professional_transactions', function (Blueprint $table) {
                    $table->unique('reference_id', 'professional_transactions_reference_id_unique');
                });
            } catch (\Throwable $e) {
                // Already unique or existing
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                try {
                    $table->dropUnique('appointments_client_nonce_unique');
                    $table->index('client_nonce', 'appointments_client_nonce_index');
                } catch (\Throwable $e) {}

                if (Schema::hasColumn('appointments', 'resolved_by_admin_id')) {
                    $table->dropColumn(['resolved_by_admin_id', 'resolution_notes', 'resolved_at']);
                }
            });
        }

        if (Schema::hasTable('professional_service_orders')) {
            Schema::table('professional_service_orders', function (Blueprint $table) {
                if (Schema::hasColumn('professional_service_orders', 'resolved_by_admin_id')) {
                    $table->dropColumn('resolved_by_admin_id');
                }
            });
        }

        if (Schema::hasTable('professional_transactions')) {
            Schema::table('professional_transactions', function (Blueprint $table) {
                try {
                    $table->dropUnique('professional_transactions_reference_id_unique');
                } catch (\Throwable $e) {}
            });
        }
    }
};
