<?php

namespace Tests\Feature\Schema;

use App\Models\Connection;
use App\Models\SchemaRegistryColumn;
use App\Services\Schema\SnapshotCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Tests\TestCase;

class PostgresIntrospectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_captures_snapshot_and_materializes_registry(): void
    {
        $dbName = 'introspect_'.strtolower(str()->ulid()->toBase32());

        $admin = new PDO('pgsql:host=postgres;dbname=postgres', 'querylayer', 'secret');
        $admin->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $admin->exec("CREATE DATABASE {$dbName}");

        try {
            $db = new PDO("pgsql:host=postgres;dbname={$dbName}", 'querylayer', 'secret');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('CREATE TABLE authors (
                id serial PRIMARY KEY,
                name text NOT NULL,
                email varchar(255) NULL,
                rating numeric(5,2) NULL,
                joined_at timestamptz NULL
            )');
            $db->exec('CREATE TABLE books (
                id serial PRIMARY KEY,
                author_id integer NOT NULL REFERENCES authors(id),
                title varchar(255) NULL,
                published_on date NULL
            )');

            $connection = Connection::query()->create([
                'name' => 'fixture-'.str()->ulid()->toBase32(),                'driver' => 'postgres',
                'host' => 'postgres',
                'port' => 5432,
                'database_name' => $dbName,
                'username' => 'querylayer',
                'password' => 'secret',
            ]);

            $snapshot = app(SnapshotCaptureService::class)->capture($connection);

            // Snapshot v1 persisted with deterministic fingerprint
            $this->assertSame(1, $snapshot->version);
            $this->assertSame(64, strlen($snapshot->fingerprint));
            $this->assertCount(2, $snapshot->raw_json['entities']);

            $authors = collect($snapshot->raw_json['entities'])->firstWhere('name', 'authors');
            $this->assertNotNull($authors);

            $columns = collect($authors['columns'])->keyBy('name');
            $this->assertSame('int', $columns['id']['type']);
            $this->assertTrue($columns['id']['primary']);
            // email is declared NULL in the DDL, therefore nullable
            $this->assertTrue($columns['email']['nullable']);
            // NOT NULL on the name column introspects as non-nullable
            $this->assertFalse($columns['name']['nullable']);
            $this->assertSame('text', $columns['name']['type']);
            $this->assertSame('decimal', $columns['rating']['type']);
            $this->assertSame('datetime', $columns['joined_at']['type']);

            // Inferred FK relationship, hint-only by origin
            $this->assertDatabaseHas('schema_registry_relationships', [
                'snapshot_version' => 1,
                'from_entity' => 'books',
                'from_column' => 'author_id',
                'to_entity' => 'authors',
                'to_column' => 'id',
                'origin' => 'inferred',
            ]);

            $this->assertDatabaseHas('schema_registry_columns', [
                'entity_name' => 'books',
                'column_name' => 'title',
                'enabled' => true,
            ]);

            // Same fingerprint => reuse, no version bump
            $again = app(SnapshotCaptureService::class)->capture($connection);
            $this->assertSame($snapshot->id, $again->id);
            $this->assertSame(1, $again->version);
        } finally {
            $admin->exec("DROP DATABASE IF EXISTS {$dbName} WITH (FORCE)");
        }
    }

    public function test_registry_configuration_is_preserved_across_drift(): void
    {
        $dbName = 'introspect_'.strtolower(str()->ulid()->toBase32());

        $admin = new PDO('pgsql:host=postgres;dbname=postgres', 'querylayer', 'secret');
        $admin->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $admin->exec("CREATE DATABASE {$dbName}");

        try {
            $db = new PDO("pgsql:host=postgres;dbname={$dbName}", 'querylayer', 'secret');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('CREATE TABLE customers (
                id serial PRIMARY KEY,
                email varchar(255) NOT NULL,
                legacy_field varchar(255) NULL
            )');

            $connection = Connection::query()->create([
                'name' => 'drift-'.str()->ulid()->toBase32(),
                'driver' => 'postgres',
                'host' => 'postgres',
                'port' => 5432,
                'database_name' => $dbName,
                'username' => 'querylayer',
                'password' => 'secret',
            ]);

            $service = app(SnapshotCaptureService::class);
            $first = $service->capture($connection);

            $this->seedRegistryTweaks($connection->id, (int) $first->version);

            // Drift: schema change
            $db->exec('ALTER TABLE customers ADD COLUMN phone varchar(255) NULL');
            $db->exec('DROP TABLE customers CASCADE'); // anti-fk table with dropped constraint? keep simple: no FKs
            $db->exec('CREATE TABLE customers (
                id serial PRIMARY KEY,
                email varchar(255) NOT NULL,
                phone varchar(255) NULL
            )');

            $second = $service->capture($connection);

            $this->assertSame(2, $second->version);

            // email stays disabled, description carries over; dropped column is gone; new column enabled
            $this->assertDatabaseHas('schema_registry_columns', [
                'snapshot_version' => 2,
                'entity_name' => 'customers',
                'column_name' => 'email',
                'enabled' => false,
                'description' => 'Contact email for customers',
            ]);
            $this->assertDatabaseMissing('schema_registry_columns', [
                'snapshot_version' => 2,
                'entity_name' => 'customers',
                'column_name' => 'legacy_field',
            ]);
            $this->assertDatabaseHas('schema_registry_columns', [
                'snapshot_version' => 2,
                'entity_name' => 'customers',
                'column_name' => 'phone',
                'enabled' => true,
            ]);
        } finally {
            $admin->exec("DROP DATABASE IF EXISTS {$dbName} WITH (FORCE)");
        }
    }

    private function seedRegistryTweaks(string $connectionId, int $version): void
    {
        SchemaRegistryColumn::query()->updateOrCreate([
            'connection_id' => $connectionId,
            'snapshot_version' => $version,
            'entity_name' => 'customers',
            'column_name' => 'email',
        ], [
            'enabled' => false,
            'description' => 'Contact email for customers',
        ]);
    }
}
