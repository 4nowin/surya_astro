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
        Schema::create('astrology_calculation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 64)->unique();
            $table->string('calculation_type', 64)->index();
            $table->string('canonical_key', 64)->index();
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->string('worker_id', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('result_cache_key', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['calculation_type', 'canonical_key']);
            $table->index(['status', 'lease_expires_at']);
            $table->index(['canonical_key', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('astrology_calculation_jobs');
    }
};
