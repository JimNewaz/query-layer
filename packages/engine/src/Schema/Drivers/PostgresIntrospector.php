<?php

namespace QueryLayer\Engine\Schema\Drivers;

use PDO;
use QueryLayer\Engine\Schema\ColumnType;
use QueryLayer\Engine\Schema\Dto\ColumnDto;
use QueryLayer\Engine\Schema\Dto\RelationshipDto;
use QueryLayer\Engine\Schema\Dto\SchemaSnapshotDto;
use QueryLayer\Engine\Schema\Dto\TableDto;
use QueryLayer\Engine\Schema\SchemaIntrospectorInterface;

/**
 * PostgreSQL introspection driver (PLAN §5.1).
 *
 * Portable basics come from information_schema; FK relationships come from
 * pg_catalog/pg_constraint natively. Everything maps into the closed DTO vocabulary.
 */
final class PostgresIntrospector implements SchemaIntrospectorInterface
{
    /** @var list<string> */
    private const EXCLUDED_SCHEMAS = ['pg_catalog', 'information_schema'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $databaseName,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function introspect(): SchemaSnapshotDto
    {
        $tables = $this->baseTables();
        $columns = $this->columns($tables);
        $primaryKeys = $this->primaryKeys();

        $entities = [];
        foreach ($tables as $tableName) {
            $tableColumns = [];
            foreach ($columns[$tableName] ?? [] as $column) {
                $tableColumns[] = new ColumnDto(
                    name: $column['name'],
                    type: $this->mapType($column['data_type'], $column['udt_name']),
                    nullable: $column['is_nullable'] === 'YES',
                    primary: in_array($column['name'], $primaryKeys[$tableName] ?? [], true),
                );
            }

            $entities[] = new TableDto($tableName, $tableColumns);
        }

        $relationships = [];
        foreach ($this->foreignKeys() as $foreignKey) {
            $relationships[] = new RelationshipDto(
                fromEntity: $foreignKey['from_table'],
                fromColumn: $foreignKey['from_column'],
                toEntity: $foreignKey['to_table'],
                toColumn: $foreignKey['to_column'],
            );
        }

        return new SchemaSnapshotDto($this->databaseName, $entities, $relationships);
    }

    /**
     * @return list<string> table names, alphabetically sorted
     */
    private function baseTables(): array
    {
        $excluded = implode(', ', array_map(
            static fn (string $schema): string => '?',
            self::EXCLUDED_SCHEMAS,
        ));

        $statement = $this->pdo->prepare(
            "SELECT table_name
             FROM information_schema.tables
             WHERE table_type = 'BASE TABLE'
               AND table_schema NOT IN ($excluded)
             ORDER BY table_name",
        );

        foreach (self::EXCLUDED_SCHEMAS as $index => $schema) {
            $statement->bindValue($index + 1, $schema);
        }

        $statement->execute();

        /** @var list<string> */
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, list<array{name: string, data_type: string, udt_name: string, is_nullable: string}>>
     */
    private function columns(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $statement = $this->pdo->prepare(
            "SELECT table_name, column_name AS name, data_type, udt_name, is_nullable
             FROM information_schema.columns
             WHERE table_name IN ($placeholders)
               AND table_schema NOT IN ('pg_catalog', 'information_schema')
             ORDER BY table_name, ordinal_position",
        );
        foreach (array_values($tables) as $index => $tableName) {
            $statement->bindValue($index + 1, $tableName);
        }
        $statement->execute();

        $grouped = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            /** @var array{name: string, data_type: string, udt_name: string, is_nullable: string} $row */
            $grouped[$row['table_name']][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<string, list<string>>
     */
    private function primaryKeys(): array
    {
        $excluded = implode(', ', array_map(
            static fn (): string => '?',
            self::EXCLUDED_SCHEMAS,
        ));

        $statement = $this->pdo->prepare(
            "SELECT tc.table_name, kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.constraint_schema = kcu.constraint_schema
             WHERE tc.constraint_type = 'PRIMARY KEY'
               AND tc.table_schema NOT IN ($excluded)",
        );

        foreach (self::EXCLUDED_SCHEMAS as $index => $schema) {
            $statement->bindValue($index + 1, $schema);
        }

        $statement->execute();

        $grouped = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[$row['table_name']][] = $row['column_name'];
        }

        return $grouped;
    }

    /**
     * Native pg_catalog read: FK constraints are hints only (PLAN §5.2).
     *
     * @return list<array{from_table: string, from_column: string, to_table: string, to_column: string}>
     */
    private function foreignKeys(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                src_cls.relname  AS from_table,
                src_attr.attname AS from_column,
                ref_cls.relname  AS to_table,
                ref_attr.attname AS to_column
             FROM pg_constraint src
             JOIN pg_class src_cls   ON src_cls.oid = src.conrelid
             JOIN pg_namespace ns    ON ns.oid = src_cls.relnamespace
             JOIN pg_class ref_cls   ON ref_cls.oid = src.confrelid
             JOIN unnest(src.conkey) WITH ORDINALITY AS src_cols(attnum, ord) ON true
             JOIN unnest(src.confkey) WITH ORDINALITY AS ref_cols(attnum, ord) ON ref_cols.ord = src_cols.ord
             JOIN pg_attribute src_attr ON src_attr.attrelid = src.conrelid AND src_attr.attnum = src_cols.attnum
             JOIN pg_attribute ref_attr ON ref_attr.attrelid = src.confrelid AND ref_attr.attnum = ref_cols.attnum
             WHERE src.contype = 'f'
               AND ns.nspname NOT IN ('pg_catalog', 'information_schema')
             ORDER BY from_table, from_column",
        );
        $statement->execute();

        /** @var list<array{from_table: string, from_column: string, to_table: string, to_column: string}> */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function mapType(string $dataType, string $udtName): ColumnType
    {
        return match ($dataType) {
            'smallint', 'integer', 'bigint' => ColumnType::Int,
            'numeric', 'real', 'double precision', 'money' => ColumnType::Decimal,
            'character varying', 'character' => ColumnType::String,
            'text' => ColumnType::Text,
            'boolean' => ColumnType::Bool,
            'date' => ColumnType::Date,
            'timestamp without time zone', 'timestamp with time zone' => ColumnType::Datetime,
            'uuid' => ColumnType::Uuid,
            'json', 'jsonb' => ColumnType::Json,
            'bytea' => ColumnType::Blob,
            // USER-DEFINED (enums, domains, custom types) degrades safely: plannable, flagged as unknown
            default => $dataType === 'time without time zone' ? ColumnType::String : ColumnType::Unknown,
        };
    }
}
