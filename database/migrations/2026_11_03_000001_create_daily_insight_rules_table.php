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
        Schema::create('daily_insight_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_identifier', 64)->unique();
            $table->string('category', 32)->index(); // overall, career, relationships, finance, wellbeing, opportunities_cautions, guidance
            $table->string('planet', 16)->index();   // sun, moon, mars, mercury, jupiter, venus, saturn, rahu, ketu
            $table->unsignedTinyInteger('house')->index(); // 1 to 12
            $table->boolean('is_retrograde')->nullable(); // null = any, true = retrograde only, false = direct only
            $table->string('tone', 16)->default('favourable'); // favourable, mixed, cautious
            $table->string('headline_en', 191);
            $table->string('headline_hi', 191);
            $table->text('interpretation_en');
            $table->text('interpretation_hi');
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['category', 'planet', 'house', 'is_active'], 'idx_cat_planet_house_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_insight_rules');
    }
};
