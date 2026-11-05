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
        Schema::create('kundli_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // 1:1 UNIQUE relationship to authoritative report purchase debit record
            $table->foreignId('report_purchase_id')
                ->unique()
                ->constrained('report_purchases')
                ->restrictOnDelete();

            // Versioning & Snapshot Parameters for Exact Reproducibility
            $table->json('birth_payload')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('ayanamsa_id', 30)->default('lahiri_true_chitra');
            $table->string('ayanamsa_version', 10)->default('1.0.0');
            $table->string('calculation_engine_version', 10)->default('1.0.0');
            $table->string('interpretation_rule_version', 10)->default('1.0.0');
            $table->string('report_template_version', 10)->default('1.0.0');

            // Delivery & Lifecycle State
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('file_path', 255)->nullable();
            $table->char('file_hash', 64)->nullable(); // SHA-256 of generated PDF
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->unsignedTinyInteger('page_count')->nullable();
            $table->unsignedInteger('generation_duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('download_count')->default(0);

            // Distinct Expiration Timestamps
            $table->timestamp('pdf_retained_until')->nullable();
            $table->timestamp('regeneration_eligible_until')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('uuid', 'idx_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kundli_reports');
    }
};
