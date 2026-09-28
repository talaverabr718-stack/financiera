<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

abstract class DemoSeeder extends Seeder
{
    protected function ensureDemoEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Los seeders de demostración están bloqueados en producción.');
        }
    }
}
