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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string("image")->nullable();
            $table->string("title");
            $table->string("language")->default('en');
            $table->string("tag");
            $table->text("excerpt")->nullable();
            $table->longText("description")->nullable();
            $table->integer("price")->default(0);
            $table->integer("original_price")->default(0);
            $table->boolean("active")->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
