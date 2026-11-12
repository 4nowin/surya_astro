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
        Schema::create('professional_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('category', 64)->default('astrologer');
            $table->string('specialization', 191)->nullable();
            $table->string('pricing_model', 32)->default('fixed'); // fixed, hourly, starting_from, custom
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 8)->default('INR');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->json('delivery_methods')->nullable(); // ['online', 'phone_call', 'in_person', 'home_visit', 'at_location', 'custom']
            $table->boolean('is_active')->default(true);
            $table->text('preparation_instructions')->nullable();
            $table->text('additional_info')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
            $table->index(['admin_id', 'is_active']);
            $table->index(['admin_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_services');
    }
};
