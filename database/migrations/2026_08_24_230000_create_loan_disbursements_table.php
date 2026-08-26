<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('number')->unique();
            $table->foreignId('credit_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->date('disbursed_at');
            $table->foreignId('disbursed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_disbursements');
    }
};
