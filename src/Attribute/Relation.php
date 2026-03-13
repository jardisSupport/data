<?php

declare(strict_types=1);

namespace JardisSupport\Data\Attribute;

/**
 * Marks a property as a relation to another aggregate entity.
 *
 * This attribute stores metadata about the relationship type (one-to-one or one-to-many)
 * and the target entity class.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Relation
{
    /**
     * @param 'one'|'many' $type Relationship type: 'one' for single entity, 'many' for collection
     * @param class-string $target Fully qualified class name of the target entity
     */
    public function __construct(
        public string $type,
        public string $target,
    ) {
    }
}
