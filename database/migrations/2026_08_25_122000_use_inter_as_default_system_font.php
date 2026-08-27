<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'font_family'], ['group' => 'appearance', 'value' => 'inter', 'type' => 'string', 'updated_by' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void {}
};
