<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('delinquency_balance', 18, 2)->default(0)->after('fee_balance');
            $table->date('maturity_date')->nullable()->after('disbursed_at');
            $table->timestamp('closed_at')->nullable()->after('maturity_date');
            $table->foreignId('restructured_from_id')->nullable()->after('credit_application_id')
                ->constrained('loans')->restrictOnDelete();
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->decimal('principal_paid', 18, 2)->default(0)->after('delinquency_due');
            $table->decimal('interest_paid', 18, 2)->default(0)->after('principal_paid');
            $table->decimal('fees_paid', 18, 2)->default(0)->after('interest_paid');
            $table->decimal('delinquency_paid', 18, 2)->default(0)->after('fees_paid');
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->json('policy_snapshot')->nullable()->after('application_order');
            $table->unique(
                ['payment_id', 'installment_id', 'component'],
                'payment_installment_component_unique'
            );
        });

        Schema::create('delinquency_accruals', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->constrained('loan_installments')->restrictOnDelete();
            $table->date('accrual_date');
            $table->decimal('base_amount', 18, 2);
            $table->decimal('rate', 12, 6);
            $table->string('method');
            $table->unsignedInteger('days_overdue');
            $table->decimal('amount', 18, 2);
            $table->json('policy_snapshot');
            $table->string('status')->default('posted')->index();
            $table->foreignId('reversal_of_id')->nullable()->unique()
                ->constrained('delinquency_accruals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['loan_id', 'accrual_date']);
            $table->index(['installment_id', 'accrual_date']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('event_hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'audit_subject_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('delinquency_accruals');

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropUnique('payment_installment_component_unique');
            $table->dropColumn('policy_snapshot');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn(['principal_paid', 'interest_paid', 'fees_paid', 'delinquency_paid']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restructured_from_id');
            $table->dropColumn(['delinquency_balance', 'maturity_date', 'closed_at']);
        });
    }
};
