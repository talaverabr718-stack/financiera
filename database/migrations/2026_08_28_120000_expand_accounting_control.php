<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on')->unique();
            $table->date('ends_on')->unique();
            $table->string('status')->default('open')->index();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('accounting_period_id')->nullable()->after('date')->constrained()->restrictOnDelete();
            $table->string('document_type')->nullable()->after('reference');
            $table->string('document_number')->nullable()->after('document_type');
            $table->string('counterparty_name')->nullable()->after('document_number');
            $table->string('counterparty_ruc', 30)->nullable()->after('counterparty_name');
            $table->foreignId('posted_by_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
            $table->index(['document_type', 'document_number']);
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('account_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('cost_center_id'));
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['document_type', 'document_number']);
            $table->dropConstrainedForeignId('posted_by_id');
            $table->dropConstrainedForeignId('accounting_period_id');
            $table->dropColumn(['document_type', 'document_number', 'counterparty_name', 'counterparty_ruc']);
        });
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('accounting_periods');
    }
};
