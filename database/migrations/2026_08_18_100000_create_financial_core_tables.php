<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('full_name');
            $table->string('identity_number')->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address');
            $table->string('department')->nullable();
            $table->string('municipality')->nullable();
            $table->string('neighborhood')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('economic_activity')->nullable();
            $table->string('workplace')->nullable();
            $table->decimal('estimated_income', 18, 2)->nullable();
            $table->decimal('estimated_expenses', 18, 2)->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('client_portfolio_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('seller_profiles')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('active_guard')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'active_guard']);
            $table->index(['seller_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portfolio_assignments');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('branches');
    }
};
