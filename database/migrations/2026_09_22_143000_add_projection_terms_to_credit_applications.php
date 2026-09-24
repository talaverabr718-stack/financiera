<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_applications', function (Blueprint $table): void {
            $table->unsignedInteger('term_value')->nullable()->after('term');
            $table->string('term_unit', 16)->nullable()->after('term_value');
            $table->decimal('total_interest', 18, 2)->nullable()->after('interest_rate');
            $table->decimal('total_payable', 18, 2)->nullable()->after('total_interest');
        });
    }

    public function down(): void
    {
        Schema::table('credit_applications', function (Blueprint $table): void {
            $table->dropColumn(['term_value', 'term_unit', 'total_interest', 'total_payable']);
        });
    }
};