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
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'client_nonce')) {
                    $table->string('client_nonce', 191)->nullable()->after('escrow_transaction_id');
                    $table->index('client_nonce');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (Schema::hasColumn('appointments', 'client_nonce')) {
                    $table->dropIndex(['client_nonce']);
                    $table->dropColumn('client_nonce');
                }
            });
        }
    }
};
