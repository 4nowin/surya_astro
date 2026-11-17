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
        // 1. Extend appointments table with consumer user link, SLAs, cancellation and no-show policies
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('admin_id');
                    $table->index('user_id');
                }
                if (!Schema::hasColumn('appointments', 'acceptance_deadline_at')) {
                    $table->timestamp('acceptance_deadline_at')->nullable()->after('end_at');
                }
                if (!Schema::hasColumn('appointments', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('acceptance_deadline_at');
                }
                if (!Schema::hasColumn('appointments', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('accepted_at');
                }
                if (!Schema::hasColumn('appointments', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_at');
                }
                if (!Schema::hasColumn('appointments', 'cancelled_by')) {
                    $table->string('cancelled_by', 32)->nullable()->after('cancellation_reason'); // user, astrologer, admin, system
                }
                if (!Schema::hasColumn('appointments', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                }
                if (!Schema::hasColumn('appointments', 'no_show_reported_at')) {
                    $table->timestamp('no_show_reported_at')->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('appointments', 'no_show_reported_by')) {
                    $table->string('no_show_reported_by', 32)->nullable()->after('no_show_reported_at'); // user, astrologer
                }
                if (!Schema::hasColumn('appointments', 'no_show_notes')) {
                    $table->text('no_show_notes')->nullable()->after('no_show_reported_by');
                }
                if (!Schema::hasColumn('appointments', 'no_show_review_status')) {
                    $table->string('no_show_review_status', 32)->default('none')->after('no_show_notes'); // none, pending_review, resolved_refund, resolved_payout
                }
                if (!Schema::hasColumn('appointments', 'escrow_transaction_id')) {
                    $table->unsignedBigInteger('escrow_transaction_id')->nullable()->after('payment_status');
                }
                if (!Schema::hasColumn('appointments', 'base_price')) {
                    $table->decimal('base_price', 10, 2)->nullable()->default(0)->after('escrow_transaction_id');
                }
                if (!Schema::hasColumn('appointments', 'platform_markup')) {
                    $table->decimal('platform_markup', 10, 2)->nullable()->default(0)->after('base_price');
                }
                if (!Schema::hasColumn('appointments', 'customer_price')) {
                    $table->decimal('customer_price', 10, 2)->nullable()->default(0)->after('platform_markup');
                }
            });
        }

        // 2. Extend professional_services with custom deliverable timeframe
        if (Schema::hasTable('professional_services')) {
            Schema::table('professional_services', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_services', 'delivery_timeframe_days')) {
                    $table->unsignedInteger('delivery_timeframe_days')->default(3)->after('duration_minutes');
                }
            });
        }

        // 3. Extend user_wallet_transactions with appointment, service_order, and service links
        if (Schema::hasTable('user_wallet_transactions')) {
            Schema::table('user_wallet_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('user_wallet_transactions', 'appointment_id')) {
                    $table->unsignedBigInteger('appointment_id')->nullable()->after('chat_id');
                    $table->index('appointment_id');
                }
                if (!Schema::hasColumn('user_wallet_transactions', 'service_order_id')) {
                    $table->unsignedBigInteger('service_order_id')->nullable()->after('appointment_id');
                    $table->index('service_order_id');
                }
                if (!Schema::hasColumn('user_wallet_transactions', 'service_id')) {
                    $table->unsignedBigInteger('service_id')->nullable()->after('service_order_id');
                    $table->index('service_id');
                }
                if (!Schema::hasColumn('user_wallet_transactions', 'description')) {
                    $table->string('description', 255)->nullable()->after('reference_id');
                }
            });
        }

        // 4. Extend professional_transactions with service_order_id
        if (Schema::hasTable('professional_transactions')) {
            Schema::table('professional_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_transactions', 'service_order_id')) {
                    $table->unsignedBigInteger('service_order_id')->nullable()->after('service_id');
                    $table->index('service_order_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('professional_transactions')) {
            Schema::table('professional_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('professional_transactions', 'service_order_id')) {
                    $table->dropColumn('service_order_id');
                }
            });
        }

        if (Schema::hasTable('user_wallet_transactions')) {
            Schema::table('user_wallet_transactions', function (Blueprint $table) {
                $cols = ['appointment_id', 'service_order_id', 'service_id', 'description'];
                $existing = array_filter($cols, fn ($col) => Schema::hasColumn('user_wallet_transactions', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('professional_services')) {
            Schema::table('professional_services', function (Blueprint $table) {
                if (Schema::hasColumn('professional_services', 'delivery_timeframe_days')) {
                    $table->dropColumn('delivery_timeframe_days');
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                $cols = [
                    'user_id', 'acceptance_deadline_at', 'accepted_at', 'rejected_at',
                    'rejection_reason', 'cancelled_by', 'cancelled_at', 'no_show_reported_at',
                    'no_show_reported_by', 'no_show_notes', 'no_show_review_status',
                    'escrow_transaction_id', 'base_price', 'platform_markup', 'customer_price'
                ];
                $existing = array_filter($cols, fn ($col) => Schema::hasColumn('appointments', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
