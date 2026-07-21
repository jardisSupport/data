<?php

declare(strict_types=1);

namespace JardisSupport\Data\Attribute;

/**
 * Marks a property as a primary key column.
 *
 * This attribute indicates that the property represents a primary key column in the database.
 * It can be used in combination with the Column attribute.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class PrimaryKey
{
    public function __construct(
        public bool $autoIncrement = false,
    ) {
    }
}
