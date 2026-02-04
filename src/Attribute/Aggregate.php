<?php

declare(strict_types=1);

namespace JardisSupport\Data\Attribute;

/**
 * Marks a class as an aggregate root or aggregate entity.
 *
 * This attribute stores metadata about the aggregate structure,
 * including whether this class is the root of the aggregate hierarchy.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class Aggregate
{
    public function __construct(
        public string $name,
        public bool $root = false,
    ) {
    }
}
