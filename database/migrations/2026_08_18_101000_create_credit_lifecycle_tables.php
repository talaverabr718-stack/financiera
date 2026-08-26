<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('currency', 3)->default('NIO');
            $table->json('allowed_frequencies');
            $table->json('allowed_interest_methods');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('seller_profiles')->restrictOnDelete();
            $table->foreignId('credit_product_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->decimal('requested_amount', 18, 2);
            $table->decimal('approved_amount', 18, 2)->nullable();
            $table->string('currency', 3)->default('NIO');
            $table->text('purpose');
            $table->unsignedInteger('term');
            $table->string('payment_frequency');
            $table->decimal('interest_rate', 12, 6)->nullable();
            $table->string('interest_method')->nullable();
            $table->date('proposed_first_payment_date')->nullable();
            $table->decimal('administrative_fee', 18, 2)->default(0);
            $table->json('economic_snapshot')->nullable();
            $table->text('seller_notes')->nullable();
            $table->text('analyst_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('credit_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('seller_profiles')->restrictOnDelete();
            $table->string('status')->default('approved')->index();
            $table->string('currency', 3);
            $table->decimal('principal', 18, 2);
            $table->decimal('principal_balance', 18, 2);
            $table->decimal('interest_balance', 18, 2)->default(0);
            $table->decimal('fee_balance', 18, 2)->default(0);
            $table->json('approved_terms');
            $table->date('disbursed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->date('due_date')->index();
            $table->decimal('principal_due', 18, 2);
            $table->decimal('interest_due', 18, 2);
            $table->decimal('fees_due', 18, 2)->default(0);
            $table->decimal('delinquency_due', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->unique(['loan_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('credit_products');
    }
};
