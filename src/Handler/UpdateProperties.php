<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Updates entity properties without modifying the snapshot.
 *
 * Useful for applying changes to an entity while preserving the original
 * snapshot for change tracking purposes.
 */
class UpdateProperties
{
    public function __construct(
        private readonly SetPropertyValue $setPropertyValue
    ) {
    }

    /**
     * Update entity properties without changing snapshot.
     *
     * @param object $entity The entity to update
     * @param array<string, mixed> $data Property values to update
     * @return void
     */
    public function __invoke(object $entity, array $data): void
    {
        foreach ($data as $columnName => $value) {
            ($this->setPropertyValue)($entity, $columnName, $value);
        }
    }
}
