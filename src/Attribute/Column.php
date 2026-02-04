<?php

declare(strict_types=1);

namespace JardisSupport\Data\Attribute;

/**
 * Marks a property as a database column.
 *
 * This attribute stores metadata about the database column that the property represents,
 * including type information, constraints, and default values.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $unique = false,
    ) {
    }
}
