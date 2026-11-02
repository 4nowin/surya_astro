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
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->text('love')->nullable()->change();
            $table->text('career')->nullable()->change();
            $table->text('money')->nullable()->change();
            $table->text('health')->nullable()->change();
            $table->text('travel')->nullable()->change();
            $table->string('mood')->nullable()->change();
            $table->string('lucky_number')->nullable()->change();
            $table->string('lucky_color')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horoscopes', function (Blueprint $table) {
            $table->text('love')->nullable(false)->change();
            $table->text('career')->nullable(false)->change();
            $table->text('money')->nullable(false)->change();
            $table->text('health')->nullable(false)->change();
            $table->text('travel')->nullable(false)->change();
            $table->string('mood')->nullable(false)->change();
            $table->string('lucky_number')->nullable(false)->change();
            $table->string('lucky_color')->nullable(false)->change();
        });
    }
};
