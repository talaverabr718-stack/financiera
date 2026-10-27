<?php

namespace App\Console\Commands;

use Database\Seeders\ProductionTestDataSeeder;
use Illuminate\Console\Command;

class SeedProductionTestData extends Command
{
    protected $signature = 'app:seed-test-data {--force : Autoriza la carga en producción} {--confirm= : Frase de confirmación}';

    protected $description = 'Carga datos ficticios e idempotentes para validar la financiera';

    public function handle(): int
    {
        if (app()->environment('production') && (! $this->option('force') || $this->option('confirm') !== 'CARGAR-DATOS-DE-PRUEBA')) {
            $this->error('Producción requiere --force --confirm=CARGAR-DATOS-DE-PRUEBA.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => ProductionTestDataSeeder::class, '--force' => true]);
        $this->info('Sucursal y datos ficticios cargados correctamente. La ejecución es idempotente.');

        return self::SUCCESS;
    }
}
