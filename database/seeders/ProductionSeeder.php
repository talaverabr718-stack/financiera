<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Datos estructurales únicamente. No crea usuarios, clientes, créditos ni movimientos.
     * La estructura operativa versionada se crea mediante migraciones.
     */
    public function run(): void
    {
        // Intencionalmente vacío: las migraciones son la única fuente de estructura base.
    }
}
