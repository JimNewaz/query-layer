<?php

namespace App\Services\Schema;

use App\Models\Connection;
use App\Models\SchemaRegistryColumn;
use App\Models\SchemaRegistryEntry;
use App\Models\SchemaRegistryRelationship;
use App\Models\SchemaSnapshot;
use Illuminate\Support\Facades\DB;
use PDO;
use QueryLayer\Engine\Schema\Drivers\PostgresIntrospector;
use QueryLayer\Engine\Schema\Dto\SchemaSnapshotDto;
use QueryLayer\Engine\Schema\SchemaIntrospectorInterface;
use RuntimeException;

/**
 * Captures a fresh introspection snapshot for a connection and materializes
 * the per-snapshot registry (entities, columns, inferred relationships),
 * preserving prior enable/description configuration on drift (PLAN §5).
 *
 * Same fingerprint ⇒ existing snapshot is reused; no new version is written.
 */
class SnapshotCaptureService
{
    public function capture(Connection $connection): SchemaSnapshot
    {
        $snapshotDto = $this->introspect($connection);
        $fingerprint = hash('sha256', (string) json_encode($snapshotDto->toArray()));

        $latest = SchemaSnapshot::query()
            ->where('connection_id', $connection->id)
            ->orderByDesc('version')
            ->first();

        if ($latest !== null && $latest->fingerprint === $fingerprint) {
            return $latest;
        }

        $nextVersion = ($latest?->version ?? 0) + 1;

        return DB::transaction(function () use ($connection, $snapshotDto, $fingerprint, $nextVersion, $latest): SchemaSnapshot {
            $snapshot = SchemaSnapshot::query()->create([
                'connection_id' => $connection->id,
                'version' => $nextVersion,
                'captured_at' => now(),
                'fingerprint' => $fingerprint,
                'raw_json' => $snapshotDto->toArray(),
            ]);

            $this->materializeRegistry($connection, $snapshot, $snapshotDto, $latest);

            return $snapshot;
        });
    }

    private function introspect(Connection $connection): SchemaSnapshotDto
    {
        return $this->introspector($connection)->introspect();
    }

    private function introspector(Connection $connection): SchemaIntrospectorInterface
    {
        switch ($connection->driver) {
            case 'postgres':
                return new PostgresIntrospector(
                    pdo: $this->connect($connection, 'pgsql'),
                    databaseName: $connection->database_name,
                );

            default:
                // MySQL driver lands in the M1 follow-up (PLAN O6: Postgres first)
                throw new RuntimeException("No introspection adapter implemented for driver [{$connection->driver}] yet.");
        }
    }

    private function connect(Connection $connection, string $dsPrefix): PDO
    {
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s',
            $dsPrefix,
            $connection->host,
            $connection->port,
            $connection->database_name,
        );

        if ($connection->ssl_mode !== null) {
            $dsn .= ';sslmode='.$connection->ssl_mode;
        }

        $pdo = new PDO($dsn, $connection->username, $connection->password ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);

        return $pdo;
    }

    private function materializeRegistry(
        Connection $connection,
        SchemaSnapshot $snapshot,
        SchemaSnapshotDto $snapshotDto,
        ?SchemaSnapshot $previous,
    ): void {
        // Prior configuration (enabled flags + descriptions) carries over when
        // entities/columns still exist after drift (PLAN §5.1)
        $priorEntries = [];
        $priorColumns = [];

        if ($previous !== null) {
            SchemaRegistryEntry::query()
                ->where('connection_id', $connection->id)
                ->where('snapshot_version', $previous->version)
                ->get()
                ->each(function (SchemaRegistryEntry $entry) use (&$priorEntries): void {
                    $priorEntries[$entry->entity_name] = $entry;
                });

            SchemaRegistryColumn::query()
                ->where('connection_id', $connection->id)
                ->where('snapshot_version', $previous->version)
                ->get()
                ->each(function (SchemaRegistryColumn $column) use (&$priorColumns): void {
                    $priorColumns["{$column->entity_name}.{$column->column_name}"] = $column;
                });
        }

        foreach ($snapshotDto->entities as $entity) {
            $priorEntry = $priorEntries[$entity->name] ?? null;

            SchemaRegistryEntry::query()->create([
                'connection_id' => $connection->id,
                'snapshot_version' => $snapshot->version,
                'entity_name' => $entity->name,
                'enabled' => $priorEntry?->enabled ?? true,
                'description' => $priorEntry?->description,
            ]);

            foreach ($entity->columns as $column) {
                $priorColumn = $priorColumns["{$entity->name}.{$column->name}"] ?? null;

                SchemaRegistryColumn::query()->create([
                    'connection_id' => $connection->id,
                    'snapshot_version' => $snapshot->version,
                    'entity_name' => $entity->name,
                    'column_name' => $column->name,
                    'enabled' => $priorColumn?->enabled ?? true,
                    'description' => $priorColumn?->description,
                ]);
            }
        }

        foreach ($snapshotDto->relationships as $relationship) {
            SchemaRegistryRelationship::query()->create([
                'connection_id' => $connection->id,
                'snapshot_version' => $snapshot->version,
                'from_entity' => $relationship->fromEntity,
                'from_column' => $relationship->fromColumn,
                'to_entity' => $relationship->toEntity,
                'to_column' => $relationship->toColumn,
                'origin' => SchemaRegistryRelationship::ORIGIN_INFERRED,
                'enabled' => true,
            ]);
        }
    }
}
