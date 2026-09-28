<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

#[Group('mysql')]
class MySqlPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_data_survives_a_mysql_connection_reconnect(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere una base MySQL exclusiva de pruebas.');
        }

        $user = User::factory()->create();
        DB::purge();
        DB::reconnect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }
}
