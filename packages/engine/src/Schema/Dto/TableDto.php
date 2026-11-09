<?php

namespace QueryLayer\Engine\Schema\Dto;

/**
 * A table as normalized by a schema introspector.
 */
final readonly class TableDto
{
    /**
     * @param  list<ColumnDto>  $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => array_map(
                static fn (ColumnDto $column): array => $column->toArray(),
                $this->columns,
            ),
        ];
    }
}
