<?php

namespace QueryLayer\Engine\Schema\Dto;

/**
 * A relationship discovered by introspection (always origin: inferred).
 * FKS are hints only — registry entries (PLAN §5.2) decide what is trusted.
 */
final readonly class RelationshipDto
{
    public function __construct(
        public string $fromEntity,
        public string $fromColumn,
        public string $toEntity,
        public string $toColumn,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'from_entity' => $this->fromEntity,
            'from_column' => $this->fromColumn,
            'to_entity' => $this->toEntity,
            'to_column' => $this->toColumn,
        ];
    }
}
