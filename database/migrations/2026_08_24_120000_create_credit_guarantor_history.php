<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('full_name');
            $table->string('identity_number')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::table('credit_applications', function (Blueprint $table) {
            $table->boolean('requires_guarantor')->default(false)->after('economic_snapshot');
        });

        Schema::create('credit_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_application_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('guarantor_id')->constrained()->restrictOnDelete();
            $table->decimal('guaranteed_amount', 18, 2);
            $table->string('guarantee_type')->default('personal');
            $table->string('status')->default('proposed')->index();
            $table->string('relationship')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->text('analyst_notes')->nullable();
            $table->string('signed_document_path')->nullable();
            $table->timestamps();
            $table->unique(['credit_application_id', 'guarantor_id']);
            $table->index(['guarantor_id', 'status']);
        });

        Schema::create('guarantor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_guarantor_id')->constrained()->restrictOnDelete();
            $table->string('occupation')->nullable();
            $table->string('workplace')->nullable();
            $table->text('workplace_address')->nullable();
            $table->decimal('monthly_income', 18, 2)->default(0);
            $table->decimal('other_income', 18, 2)->default(0);
            $table->decimal('monthly_expenses', 18, 2)->default(0);
            $table->json('assets_snapshot')->nullable();
            $table->boolean('has_overdue_obligations')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('evaluated_at');
            $table->timestamps();
        });

        Schema::create('guarantor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantor_id')->constrained()->restrictOnDelete();
            $table->foreignId('credit_guarantor_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('client_guarantors')) {
            DB::table('client_guarantors')->orderBy('id')->each(function ($legacy): void {
                DB::table('guarantors')->insert([
                    'legacy_client_id' => $legacy->client_id,
                    'full_name' => $legacy->full_name,
                    'identity_number' => $legacy->identity_number,
                    'phone' => $legacy->phone,
                    'address' => $legacy->address,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantor_documents');
        Schema::dropIfExists('guarantor_evaluations');
        Schema::dropIfExists('credit_guarantors');
        Schema::table('credit_applications', fn (Blueprint $table) => $table->dropColumn('requires_guarantor'));
        Schema::dropIfExists('guarantors');
    }
};
