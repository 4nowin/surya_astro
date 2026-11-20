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
        if (!Schema::hasTable('jajman_kundli_reports')) {
            Schema::create('jajman_kundli_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->index();
                $table->unsignedBigInteger('jajman_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('report_type', 32)->default('basic');
                $table->string('language', 10)->default('en');
                $table->string('title', 191)->nullable();
                $table->string('person_name', 191)->nullable();
                $table->string('file_disk', 32)->default('local');
                $table->string('file_path', 255);
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->json('birth_snapshot');
                $table->json('calculation_settings')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                $table->foreign('jajman_id')->references('id')->on('jajmans')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

                $table->index(['admin_id', 'jajman_id']);
                $table->index(['jajman_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jajman_kundli_reports');
    }
};
