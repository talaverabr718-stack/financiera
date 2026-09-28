<?php

namespace Database\Seeders;

class DemoDatabaseSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->ensureDemoEnvironment();
        $this->call(OperationsBoardSeeder::class);
    }
}
