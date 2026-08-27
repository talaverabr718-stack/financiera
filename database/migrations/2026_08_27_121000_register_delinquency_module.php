<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_modules')->insertOrIgnore([
            'key' => 'delinquency',
            'name' => 'Clientes en mora',
            'description' => 'Detección y seguimiento de expedientes de mora por crédito',
            'is_enabled' => true,
            'is_visible' => true,
            'sort_order' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_modules')->where('key', 'delinquency')->delete();
    }
};
