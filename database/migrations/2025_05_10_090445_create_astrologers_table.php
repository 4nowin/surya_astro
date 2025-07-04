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
        Schema::create('astrologers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->onDelete('cascade');
            $table->string("image")->nullable();
            $table->string("name");
            $table->string("language")->default('en');
            $table->string("astrologer_language");
            $table->string("expertise");
            $table->string("experience");
            $table->text("excerpt")->nullable();
            $table->longText("description")->nullable();
            $table->integer("chat_minutes")->default(0);
            $table->integer("call_minutes")->default(0);
            $table->integer("price")->default(0);
            $table->integer("call_price")->default(0);
            $table->integer("original_price")->default(0);
            $table->enum('status', ['online', 'offline'])->default('offline');
            $table->index('status');
            $table->boolean("active")->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('astrologers');
    }
};
