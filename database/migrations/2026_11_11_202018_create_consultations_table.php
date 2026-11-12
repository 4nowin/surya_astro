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
        if (!Schema::hasTable('consultations')) {
            Schema::create('consultations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->index(); // The professional
                $table->unsignedBigInteger('jajman_id')->nullable()->index(); // Linked Jajman
                $table->unsignedBigInteger('chat_session_id')->nullable()->index(); // Optional online chat session link
                $table->string('client_name');
                $table->string('type', 50)->default('online_chat'); // online_chat, in_person, phone_call, ritual_puja, vastu_visit
                $table->string('status', 30)->default('completed'); // completed, in_progress, scheduled, cancelled
                $table->timestamp('consultation_date')->useCurrent();
                $table->integer('duration_minutes')->nullable();
                $table->decimal('fee_charged', 10, 2)->nullable();
                $table->text('summary')->nullable(); // Professional summary / notes
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                if (Schema::hasTable('chat_sessions')) {
                    $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->nullOnDelete();
                }
                if (Schema::hasTable('jajmans')) {
                    $table->foreign('jajman_id')->references('id')->on('jajmans')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
