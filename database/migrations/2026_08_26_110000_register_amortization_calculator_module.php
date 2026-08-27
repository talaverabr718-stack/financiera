<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_modules')->insertOrIgnore([
            'key' => 'amortization',
            'name' => 'Calculadora de amortización',
            'description' => 'Simulación no vinculante de cuotas, capital e intereses',
            'is_enabled' => true,
            'is_visible' => true,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_modules')->where('key', 'amortization')->delete();
    }
};
