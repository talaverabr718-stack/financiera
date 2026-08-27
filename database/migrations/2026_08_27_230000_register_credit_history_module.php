<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_modules')->insertOrIgnore([
            'key' => 'credit_history',
            'name' => 'Historial crediticio',
            'description' => 'Créditos, pagos y desbloqueo de un nuevo crédito al cancelar el vigente',
            'is_enabled' => true,
            'is_visible' => true,
            'sort_order' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_modules')->where('key', 'credit_history')->delete();
    }
};
