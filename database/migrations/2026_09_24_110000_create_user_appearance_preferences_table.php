<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_appearance_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme', 16);
            $table->string('primary_color', 7);
            $table->string('sidebar_color', 7);
            $table->string('accent_color', 7);
            $table->string('background_color', 7);
            $table->string('font_family', 32);
            $table->string('density', 16);
            $table->string('border_radius', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_appearance_preferences');
    }
};
