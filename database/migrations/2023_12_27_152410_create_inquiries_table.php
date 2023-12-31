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
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("email")->nullable();
            $table->string("phone");
            $table->string("gender");
            $table->string("country")->nullable();
            $table->string("date_of_birth")->nullable();
            $table->string("place_of_birth")->nullable();
            $table->string("for");
            $table->string("type")->nullable();
            $table->string("from")->nullable();
            $table->integer('status')->default(0);
            $table->longText("message")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
