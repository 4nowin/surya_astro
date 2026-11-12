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
        // 1. Create professional transactions table
        if (!Schema::hasTable('professional_transactions')) {
            Schema::create('professional_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id');
                $table->unsignedBigInteger('jajman_id')->nullable();
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->unsignedBigInteger('consultation_id')->nullable();
                $table->unsignedBigInteger('parent_transaction_id')->nullable();

                $table->string('transaction_type', 32); // service_payment, appointment_advance, consultation_payment, general_payment, refund, adjustment
                $table->string('payment_method', 32); // cash, upi, bank_transfer, card, cheque, other
                $table->string('payment_status', 32)->default('completed'); // completed, pending, failed, refunded, partially_refunded
                $table->decimal('amount', 10, 2);
                $table->string('currency', 8)->default('INR');
                $table->dateTime('transaction_at');
                $table->string('reference_id', 191)->nullable(); // UTR / Cheque / Auth reference
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
                if (Schema::hasTable('jajmans')) {
                    $table->foreign('jajman_id')->references('id')->on('jajmans')->onDelete('set null');
                }
                if (Schema::hasTable('appointments')) {
                    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');
                }
                if (Schema::hasTable('consultations')) {
                    $table->foreign('consultation_id')->references('id')->on('consultations')->onDelete('set null');
                }
                $table->foreign('parent_transaction_id')->references('id')->on('professional_transactions')->onDelete('set null');

                $table->index(['admin_id', 'transaction_at']);
                $table->index(['admin_id', 'jajman_id']);
                $table->index(['admin_id', 'appointment_id']);
                $table->index(['admin_id', 'consultation_id']);
                $table->index(['admin_id', 'payment_status']);
                $table->index(['admin_id', 'transaction_type']);
            });
        }

        // 2. Add payment_status to appointments and consultations for query performance
        if (Schema::hasTable('appointments') && !Schema::hasColumn('appointments', 'payment_status')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('payment_status', 32)->default('unpaid')->after('delivery_method_snapshot');
                $table->index('payment_status');
            });
        }

        if (Schema::hasTable('consultations') && !Schema::hasColumn('consultations', 'payment_status')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->string('payment_status', 32)->default('unpaid')->after('fee_charged');
                $table->index('payment_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_transactions');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn('payment_status');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn('payment_status');
        });
    }
};
