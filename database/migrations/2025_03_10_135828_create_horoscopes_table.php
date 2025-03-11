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
        Schema::create('horoscopes', function (Blueprint $table) {
            $table->id();
            $table->string("image")->nullable();   
            $table->enum('zodiac_sign', [
                'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 
                'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
            ]); 
            $table->enum('horoscope_type', ['daily', 'weekly', 'monthly', 'yearly']); 
            $table->string("language"); 
            $table->string("zodiac"); 
            $table->string("tag"); 
            $table->text("context"); 
            $table->string("love"); 
            $table->string("career"); 
            $table->string("money"); 
            $table->string("health"); 
            $table->string("travel"); 
            $table->string("lucky_number"); 
            $table->string("lucky_color"); 
            $table->string("lucky_time")->nullable(); 
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps(); 

            // Index for faster queries
            $table->index(['language', 'horoscope_type', 'zodiac_sign', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horoscopes');
    }
};
