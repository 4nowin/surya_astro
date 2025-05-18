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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('inquiry_id');
            $table->string('payment_id');
            $table->integer('total_price');
            $table->enum('status', ['CREATED', 'PENDING', 'CONFIRMED', 'FAILED', 'CANCELLED'])->default('CREATED');
            $table->index('status');
            $table->boolean('is_checked_in')->nullable();
            $table->integer("promoter_id")->nullable()->reference("id")->on("promoters");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
