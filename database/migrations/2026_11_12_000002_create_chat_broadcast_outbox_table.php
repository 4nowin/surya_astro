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
        if (!Schema::hasTable('chat_broadcast_outbox')) {
            Schema::create('chat_broadcast_outbox', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
                $table->foreignId('chat_session_id')->constrained('chat_sessions')->onDelete('cascade');
                $table->json('payload');
                $table->unsignedInteger('attempts')->default(0);
                $table->string('status', 20)->default('pending'); // pending, processing, delivered, failed
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['status', 'attempts'], 'chat_outbox_status_attempts_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_broadcast_outbox');
    }
};
