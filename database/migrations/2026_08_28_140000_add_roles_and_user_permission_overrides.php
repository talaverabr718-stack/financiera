<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('system_module_role', function (Blueprint $table) {
            $table->foreignId('system_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_module_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->primary(['system_role_id', 'system_module_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('system_role_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('system_module_user', function (Blueprint $table) {
            $table->boolean('can_view')->nullable()->default(null)->change();
            $table->boolean('can_manage')->nullable()->default(null)->change();
        });

        DB::table('system_modules')->updateOrInsert(
            ['key' => 'settings'],
            ['name' => 'Configuración', 'description' => 'Usuarios, roles, permisos y parámetros del sistema', 'is_enabled' => true, 'is_visible' => true, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],
        );

        $roleId = DB::table('system_roles')->insertGetId([
            'key' => 'administrator',
            'name' => 'Administrador',
            'description' => 'Acceso total compatible con las cuentas existentes.',
            'is_system' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('system_modules')->pluck('id') as $moduleId) {
            DB::table('system_module_role')->insert([
                'system_role_id' => $roleId,
                'system_module_id' => $moduleId,
                'can_view' => true,
                'can_manage' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->whereNull('system_role_id')->update(['system_role_id' => $roleId]);
    }

    public function down(): void
    {
        DB::table('system_module_user')->whereNull('can_view')->update(['can_view' => true]);
        DB::table('system_module_user')->whereNull('can_manage')->update(['can_manage' => false]);

        Schema::table('system_module_user', function (Blueprint $table) {
            $table->boolean('can_view')->nullable(false)->default(true)->change();
            $table->boolean('can_manage')->nullable(false)->default(false)->change();
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('system_role_id'));
        Schema::dropIfExists('system_module_role');
        Schema::dropIfExists('system_roles');
        DB::table('system_modules')->where('key', 'settings')->delete();
    }
};
