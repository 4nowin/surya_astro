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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('rzp_payment_id')->nullable();
            $table->string('rzp_order_id');
            $table->string('payment_method');
            $table->string('rzp_signature')->nullable();
            $table->string('order_id');
            $table->string('payment_type')->nullable();
            $table->string('payment_for')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('wallet')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('amount');
            $table->enum('status', ['CREATED', 'PENDING', 'CONFIRMED', 'FAILED', 'CANCELLED'])->default('CREATED');
            $table->index('status');
            $table->timestamps();

            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
