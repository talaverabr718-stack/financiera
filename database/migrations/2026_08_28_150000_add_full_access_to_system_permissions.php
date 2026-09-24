<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_module_role', function (Blueprint $table) {
            $table->boolean('can_full')->default(false)->after('can_manage');
        });

        Schema::table('system_module_user', function (Blueprint $table) {
            $table->boolean('can_full')->nullable()->default(null)->after('can_manage');
        });

        DB::table('system_module_role')->update([
            'can_full' => DB::raw('can_manage'),
        ]);
        DB::table('system_module_user')
            ->whereNotNull('can_manage')
            ->update(['can_full' => DB::raw('can_manage')]);
    }

    public function down(): void
    {
        Schema::table('system_module_user', function (Blueprint $table) {
            $table->dropColumn('can_full');
        });
        Schema::table('system_module_role', function (Blueprint $table) {
            $table->dropColumn('can_full');
        });
    }
};
