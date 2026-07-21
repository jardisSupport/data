<?php

declare(strict_types=1);

namespace JardisSupport\Data\Attribute;

/**
 * Marks a property as a foreign key column.
 *
 * This attribute stores metadata about the foreign key relationship,
 * including the referenced table and column.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class ForeignKey
{
    public function __construct(
        public string $referencedTable,
        public string $referencedColumn,
        public string $onUpdate = 'RESTRICT',
        public string $onDelete = 'RESTRICT',
    ) {
    }
}
