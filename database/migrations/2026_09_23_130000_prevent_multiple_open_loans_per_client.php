<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('open_guard', 16)->nullable()->after('status');
        });

        DB::table('loans')
            ->whereIn('status', ['active', 'delinquent'])
            ->update(['open_guard' => 'OPEN']);

        Schema::table('loans', function (Blueprint $table) {
            $table->unique(['client_id', 'open_guard'], 'loans_one_open_per_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique('loans_one_open_per_client_unique');
            $table->dropColumn('open_guard');
        });
    }
};
