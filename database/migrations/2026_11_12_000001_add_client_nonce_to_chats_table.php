<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('chats')) {
            if (!Schema::hasColumn('chats', 'client_nonce')) {
                Schema::table('chats', function (Blueprint $table) {
                    $table->string('client_nonce', 64)->nullable()->after('sender');
                });
            }

            $existingIndexes = [];
            try {
                $indexes = DB::select("SHOW INDEX FROM chats");
                $existingIndexes = collect($indexes)->pluck('Key_name')->unique()->toArray();
            } catch (\Throwable $e) {}

            Schema::table('chats', function (Blueprint $table) use ($existingIndexes) {
                if (!in_array('chats_session_nonce_unique', $existingIndexes)) {
                    $table->unique(['chat_session_id', 'client_nonce'], 'chats_session_nonce_unique');
                }
                if (!in_array('chats_session_id_id_index', $existingIndexes)) {
                    $table->index(['chat_session_id', 'id'], 'chats_session_id_id_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('chats')) {
            $existingIndexes = [];
            try {
                $indexes = DB::select("SHOW INDEX FROM chats");
                $existingIndexes = collect($indexes)->pluck('Key_name')->unique()->toArray();
            } catch (\Throwable $e) {}

            // Ensure chat_session_id has its own index before dropping composite indexes,
            // otherwise MySQL InnoDB rejects dropping the index needed by the foreign key constraint (Error 1553).
            if (!in_array('chats_chat_session_id_foreign', $existingIndexes) && !in_array('chats_chat_session_id_index', $existingIndexes)) {
                try {
                    Schema::table('chats', function (Blueprint $table) {
                        $table->index('chat_session_id', 'chats_chat_session_id_index');
                    });
                } catch (\Throwable $e) {}
            }

            Schema::table('chats', function (Blueprint $table) {
                try {
                    $table->dropUnique('chats_session_nonce_unique');
                } catch (\Throwable $e) {}

                try {
                    $table->dropIndex('chats_session_id_id_index');
                } catch (\Throwable $e) {}

                if (Schema::hasColumn('chats', 'client_nonce')) {
                    $table->dropColumn('client_nonce');
                }
            });
        }
    }
};

