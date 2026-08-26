<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('collection_route_stop_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('seller_profiles')->restrictOnDelete();
            $table->string('outcome')->index();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 3)->default('NIO');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->date('promise_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('application_status')->default('pending')->index();
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_records');
    }
};
