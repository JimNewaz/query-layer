<?php

namespace QueryLayer\Engine\Schema\Dto;

use QueryLayer\Engine\Schema\ColumnType;

/**
 * A single column of a table, normalized to the IR type vocabulary.
 */
final readonly class ColumnDto
{
    public function __construct(
        public string $name,
        public ColumnType $type,
        public bool $nullable,
        public bool $primary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'nullable' => $this->nullable,
            'primary' => $this->primary,
        ];
    }
}
