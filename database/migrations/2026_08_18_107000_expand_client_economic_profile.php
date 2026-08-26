<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('job_position')->nullable();
            $table->text('workplace_address')->nullable();
            $table->unsignedInteger('employment_duration_months')->nullable();
            $table->decimal('other_income', 18, 2)->default(0);
            $table->string('housing_status')->nullable();
            $table->unsignedInteger('dependents')->default(0);
        });

        Schema::create('client_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('description');
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->string('ownership_status')->default('owned');
            $table->string('document_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('client_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('identity_number')->nullable()->index();
            $table->string('relationship');
            $table->string('phone');
            $table->text('address');
            $table->string('occupation')->nullable();
            $table->string('workplace')->nullable();
            $table->decimal('monthly_income', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_guarantors');
        Schema::dropIfExists('client_assets');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['job_position', 'workplace_address', 'employment_duration_months', 'other_income', 'housing_status', 'dependents']);
        });
    }
};
