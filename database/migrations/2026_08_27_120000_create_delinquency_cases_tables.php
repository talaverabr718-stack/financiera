<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delinquency_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->date('started_on');
            $table->date('oldest_due_on');
            $table->date('last_calculated_on');
            $table->date('resolved_on')->nullable();
            $table->unsignedInteger('current_days')->default(0);
            $table->unsignedInteger('total_days')->default(0);
            $table->unsignedInteger('overdue_installment_count')->default(0);
            $table->decimal('overdue_balance', 18, 2)->default(0);
            $table->foreignId('oldest_installment_id')->nullable()->constrained('loan_installments')->restrictOnDelete();
            $table->string('active_guard')->nullable();
            $table->timestamps();
            $table->index(['status', 'current_days']);
            $table->index(['status', 'overdue_balance']);
            $table->index(['started_on']);
            $table->unique(['loan_id', 'active_guard']);
        });

        Schema::create('delinquency_case_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delinquency_case_id')->constrained('delinquency_cases')->restrictOnDelete();
            $table->foreignId('loan_installment_id')->constrained('loan_installments')->restrictOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 18, 2);
            $table->decimal('amount_paid', 18, 2);
            $table->decimal('outstanding_amount', 18, 2);
            $table->unsignedInteger('days_overdue')->default(0);
            $table->timestamps();
            $table->unique(['delinquency_case_id', 'loan_installment_id'], 'delinquency_case_installment_unique');
        });

        DB::table('document_sequences')->insertOrIgnore([
            'key' => 'delinquency_case',
            'prefix' => 'MORA-',
            'next_number' => 1,
            'padding' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delinquency_case_installments');
        Schema::dropIfExists('delinquency_cases');
        DB::table('document_sequences')->where('key', 'delinquency_case')->delete();
    }
};
