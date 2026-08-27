<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('system_module_user', function (Blueprint $table) {
            $table->foreignId('system_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->primary(['system_module_id', 'user_id']);
        });

        $modules = [
            ['dashboard', 'Panel general', 'Indicadores y resumen de la operación'], ['clients', 'Clientes', 'Expedientes y asignación de cartera'],
            ['applications', 'Solicitudes', 'Solicitudes y productos crediticios'], ['loans', 'Cartera', 'Préstamos y saldos'],
            ['routes', 'Rutas', 'Planificación y clientes por ruta'], ['collections', 'Cobranza', 'Cobros, promesas y visitas'],
            ['cash', 'Caja', 'Operaciones de caja'], ['collaborators', 'Colaboradores', 'Personal de campo y capacidades'],
            ['accounting', 'Contabilidad', 'Catálogo, asientos y libros'], ['reports', 'Reportes', 'Reportería operativa y financiera'],
        ];
        foreach ($modules as $order => [$key,$name,$description]) {
            DB::table('system_modules')->insert(['key' => $key, 'name' => $name, 'description' => $description, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_module_user');
        Schema::dropIfExists('system_modules');
    }
};
