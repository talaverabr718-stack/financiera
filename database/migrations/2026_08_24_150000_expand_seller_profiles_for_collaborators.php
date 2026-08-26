<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('identity_number')->nullable()->unique()->after('code');
            $table->string('phone', 30)->nullable()->after('identity_number');
            $table->date('hired_at')->nullable()->after('phone');
            $table->json('capabilities')->nullable()->after('hired_at');
            $table->text('notes')->nullable()->after('capabilities');
        });

        DB::table('seller_profiles')->whereNull('capabilities')->update([
            'capabilities' => json_encode(['prospecting', 'credit_origination', 'collections']),
        ]);
    }

    public function down(): void
    {
        Schema::table('seller_profiles', fn (Blueprint $table) => $table->dropColumn([
            'identity_number', 'phone', 'hired_at', 'capabilities', 'notes',
        ]));
    }
};
