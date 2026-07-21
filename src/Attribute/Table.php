<?php

declare(strict_types=1);

namespace JardisSupport\Data\Attribute;

/**
 * Marks a class as a database table model.
 *
 * This attribute stores metadata about the database table that the class represents.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class Table
{
    public function __construct(
        public string $name,
        public ?string $schema = null,
    ) {
    }
}
