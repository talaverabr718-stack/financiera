<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->string('delinquency_method', 32)->nullable()->after('delinquency_balance');
            $table->decimal('delinquency_fixed_amount', 18, 2)->nullable()->after('delinquency_daily_rate');
        });

        DB::table('loans')->whereNotNull('delinquency_daily_rate')->update([
            'delinquency_method' => 'daily_percentage',
        ]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn(['delinquency_method', 'delinquency_fixed_amount']);
        });
    }
};
