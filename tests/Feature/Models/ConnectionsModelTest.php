<?php

namespace Tests\Feature\Models;

use App\Models\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionsModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_factory_creates_connection_with_encrypted_password(): void
    {
        $connection = Connection::factory()->postgres()->create();

        $this->assertDatabaseHas('connections', [
            'id' => $connection->id,
            'driver' => 'postgres',
        ]);

        // Stored ciphertext must never equal the plaintext secret
        $stored = $connection->getAttributes()['password_ciphertext'];
        $this->assertNotSame('secret', $stored);
        $this->assertStringNotContainsString('secret', $stored);

        // And the server-side accessor must round-trip it exactly
        $this->assertSame('secret', $connection->password);
    }

    public function test_connection_factory_covers_mysql_driver(): void
    {
        $connection = Connection::factory()->mysql()->create();

        $this->assertSame('mysql', $connection->driver);
        $this->assertSame('secret', $connection->password);
    }

    public function test_connection_is_soft_deletable(): void
    {
        $connection = Connection::factory()->create();

        $connection->delete();

        $this->assertSoftDeleted('connections', [
            'id' => $connection->id,
        ]);
    }
}
