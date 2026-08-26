<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('receipt_number')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->index();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->decimal('previous_balance', 18, 2);
            $table->decimal('new_balance', 18, 2);
            $table->string('status')->default('applied')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('loan_installments')->restrictOnDelete();
            $table->string('component');
            $table->decimal('amount', 18, 2);
            $table->unsignedSmallInteger('application_order');
            $table->unique(['payment_id', 'application_order']);
            $table->index(['installment_id', 'component']);
        });
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reversals');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
