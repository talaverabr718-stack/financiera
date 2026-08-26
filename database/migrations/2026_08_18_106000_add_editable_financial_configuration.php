<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_products', function (Blueprint $table) {
            $table->decimal('default_interest_rate', 12, 6)->nullable();
            $table->string('default_interest_method')->nullable();
            $table->decimal('default_administrative_fee', 18, 2)->default(0);
            $table->string('delinquency_method')->nullable();
            $table->decimal('delinquency_rate', 12, 6)->nullable();
            $table->json('payment_allocation_order')->nullable();
            $table->unsignedInteger('minimum_term')->default(1);
            $table->unsignedInteger('maximum_term')->default(60);
        });

        Schema::create('financial_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('group')->default('general')->index();
            $table->boolean('is_critical')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
        Schema::table('credit_products', function (Blueprint $table) {
            $table->dropColumn(['default_interest_rate', 'default_interest_method', 'default_administrative_fee', 'delinquency_method', 'delinquency_rate', 'payment_allocation_order', 'minimum_term', 'maximum_term']);
        });
    }
};
