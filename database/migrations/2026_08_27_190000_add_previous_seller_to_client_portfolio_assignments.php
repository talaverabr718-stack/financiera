<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_portfolio_assignments', function (Blueprint $table) {
            $table->foreignId('previous_seller_id')->nullable()->after('seller_id')->constrained('seller_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_portfolio_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_seller_id');
        });
    }
};
