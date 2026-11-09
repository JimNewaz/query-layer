<?php

namespace QueryLayer\Engine\Schema;

use QueryLayer\Engine\Schema\Dto\SchemaSnapshotDto;

/**
 * Contract every database-introspection driver implements.
 * Drivers read raw catalogs only; they never touch the registry or the LLM.
 */
interface SchemaIntrospectorInterface
{
    public function introspect(): SchemaSnapshotDto;
}
