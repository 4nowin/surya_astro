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
        Schema::table('chats', function (Blueprint $table) {
            $table->string('client_nonce', 64)->nullable()->after('sender');
            $table->unique(['chat_session_id', 'client_nonce'], 'chats_session_nonce_unique');
            $table->index(['chat_session_id', 'id'], 'chats_session_id_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique('chats_session_nonce_unique');
            $table->dropIndex('chats_session_id_id_index');
            $table->dropColumn('client_nonce');
        });
    }
};
