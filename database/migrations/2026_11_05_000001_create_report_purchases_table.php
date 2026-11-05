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
        Schema::create('report_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->enum('transaction_type', ['debit', 'compensating_refund']);
            $table->decimal('amount', 8, 2); // Always positive absolute value
            $table->string('currency', 3)->default('INR');

            // Database-level UNIQUE constraint mathematically guarantees at most one
            // compensating refund per original debit purchase record.
            $table->foreignId('related_purchase_id')
                ->nullable()
                ->unique()
                ->constrained('report_purchases')
                ->restrictOnDelete();

            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->timestamps();

            $table->index(['user_id', 'transaction_type'], 'idx_user_txn');
            $table->index('idempotency_key', 'idx_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_purchases');
    }
};
