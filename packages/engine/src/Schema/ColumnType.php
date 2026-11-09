<?php

namespace QueryLayer\Engine\Schema;

/**
 * The closed set of column types the IR v1 grammar understands.
 * Introspectors must map every external-database type into one of these.
 */
enum ColumnType: string
{
    case Int = 'int';
    case Decimal = 'decimal';
    case String = 'string';
    case Text = 'text';
    case Bool = 'bool';
    case Date = 'date';
    case Datetime = 'datetime';
    case Uuid = 'uuid';
    case Json = 'json';
    case Blob = 'blob';
    case Unknown = 'unknown';
}
