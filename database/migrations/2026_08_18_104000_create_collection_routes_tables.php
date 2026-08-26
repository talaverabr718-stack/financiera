<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_routes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->date('scheduled_date')->index();
            $table->foreignId('collector_id')->constrained('seller_profiles')->restrictOnDelete();
            $table->string('status')->default('planned')->index();
            $table->time('starts_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('collection_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('status')->default('pending')->index();
            $table->timestamp('visited_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['collection_route_id', 'client_id']);
            $table->unique(['collection_route_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_route_stops');
        Schema::dropIfExists('collection_routes');
    }
};
