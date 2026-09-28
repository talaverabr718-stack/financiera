<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * El seeder por defecto es seguro para producción y no incorpora datos demostrativos.
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);
    }
}
