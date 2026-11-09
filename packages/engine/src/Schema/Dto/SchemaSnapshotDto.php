<?php

namespace QueryLayer\Engine\Schema\Dto;

/**
 * Driver-independent schema snapshot: the canonical introspection output
 * that gets persisted into schema_snapshots.raw_json (PLAN §3, §5).
 */
final readonly class SchemaSnapshotDto
{
    /**
     * @param  list<TableDto>  $entities
     * @param  list<RelationshipDto>  $relationships
     */
    public function __construct(
        public string $database,
        public array $entities,
        public array $relationships,
    ) {}

    /**
     * Deterministic serialization — the fingerprint basis (PLAN §5.2).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'database' => $this->database,
            'entities' => array_map(
                static fn (TableDto $entity): array => $entity->toArray(),
                $this->entities,
            ),
            'relationships' => array_map(
                static fn (RelationshipDto $relationship): array => $relationship->toArray(),
                $this->relationships,
            ),
        ];
    }
}
