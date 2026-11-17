<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. One-time introductory free chat allowance per (user, astrologer)
        if (!Schema::hasTable('user_astrologer_chat_allowances')) {
            Schema::create('user_astrologer_chat_allowances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('astrologer_id')->constrained('astrologers')->onDelete('cascade');
                $table->unsignedInteger('free_messages_used')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'astrologer_id'], 'user_astrologer_allowance_unique');
            });
        }

        // 2. User wallet transaction ledger for auditable deductions & top-ups
        if (!Schema::hasTable('user_wallet_transactions')) {
            Schema::create('user_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('transaction_type', 32); // chat_message_debit, wallet_topup, refund, adjustment
                $table->decimal('amount', 10, 2);
                $table->decimal('balance_before', 10, 2);
                $table->decimal('balance_after', 10, 2);
                $table->unsignedBigInteger('chat_id')->nullable();
                $table->unsignedBigInteger('chat_session_id')->nullable();
                $table->unsignedBigInteger('astrologer_id')->nullable();
                $table->string('reference_id', 191)->unique();
                $table->string('status', 32)->default('completed'); // completed, failed, refunded
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index('chat_session_id');
                $table->index('chat_id');
            });
        }

        // 3. Extend professional_services with chat-specific attributes
        if (Schema::hasTable('professional_services')) {
            Schema::table('professional_services', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_services', 'is_free_chat')) {
                    $table->boolean('is_free_chat')->default(false)->after('is_active');
                }
                if (!Schema::hasColumn('professional_services', 'max_message_characters')) {
                    $table->unsignedInteger('max_message_characters')->default(1000)->after('is_free_chat');
                }
            });
        }

        // 4. Extend professional_transactions with user, service, and chat-earning snapshots
        if (Schema::hasTable('professional_transactions')) {
            Schema::table('professional_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_transactions', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('jajman_id');
                }
                if (!Schema::hasColumn('professional_transactions', 'service_id')) {
                    $table->unsignedBigInteger('service_id')->nullable()->after('consultation_id');
                }
                if (!Schema::hasColumn('professional_transactions', 'chat_session_id')) {
                    $table->unsignedBigInteger('chat_session_id')->nullable()->after('service_id');
                }
                if (!Schema::hasColumn('professional_transactions', 'chat_id')) {
                    $table->unsignedBigInteger('chat_id')->nullable()->after('chat_session_id');
                }
                if (!Schema::hasColumn('professional_transactions', 'gross_amount')) {
                    $table->decimal('gross_amount', 10, 2)->nullable()->after('amount');
                }
                if (!Schema::hasColumn('professional_transactions', 'platform_fee')) {
                    $table->decimal('platform_fee', 10, 2)->nullable()->after('gross_amount');
                }
                if (!Schema::hasColumn('professional_transactions', 'net_amount')) {
                    $table->decimal('net_amount', 10, 2)->nullable()->after('platform_fee');
                }

                $table->index(['admin_id', 'chat_session_id'], 'prof_tx_admin_chat_session_idx');
                $table->index(['admin_id', 'chat_id'], 'prof_tx_admin_chat_idx');
            });
        }

        // 5. Extend chats with immutable billing snapshot
        if (Schema::hasTable('chats')) {
            Schema::table('chats', function (Blueprint $table) {
                if (!Schema::hasColumn('chats', 'billing_type')) {
                    $table->string('billing_type', 32)->default('free')->after('client_nonce');
                }
                if (!Schema::hasColumn('chats', 'base_price')) {
                    $table->decimal('base_price', 10, 2)->default(0.00)->after('billing_type');
                }
                if (!Schema::hasColumn('chats', 'platform_markup')) {
                    $table->decimal('platform_markup', 10, 2)->default(0.00)->after('base_price');
                }
                if (!Schema::hasColumn('chats', 'customer_price')) {
                    $table->decimal('customer_price', 10, 2)->default(0.00)->after('platform_markup');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_wallet_transactions');
        Schema::dropIfExists('user_astrologer_chat_allowances');

        if (Schema::hasTable('chats')) {
            Schema::table('chats', function (Blueprint $table) {
                $columns = ['billing_type', 'base_price', 'platform_markup', 'customer_price'];
                $existing = array_filter($columns, fn ($col) => Schema::hasColumn('chats', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('professional_transactions')) {
            Schema::table('professional_transactions', function (Blueprint $table) {
                try {
                    $table->dropIndex('prof_tx_admin_chat_session_idx');
                    $table->dropIndex('prof_tx_admin_chat_idx');
                } catch (\Throwable $e) {}

                $columns = ['user_id', 'service_id', 'chat_session_id', 'chat_id', 'gross_amount', 'platform_fee', 'net_amount'];
                $existing = array_filter($columns, fn ($col) => Schema::hasColumn('professional_transactions', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('professional_services')) {
            Schema::table('professional_services', function (Blueprint $table) {
                $columns = ['is_free_chat', 'max_message_characters'];
                $existing = array_filter($columns, fn ($col) => Schema::hasColumn('professional_services', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
