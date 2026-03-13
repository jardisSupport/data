<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Hydrates an entity with data from database.
 */
class HydrateEntity
{
    public function __construct(
        private readonly SetPropertyValue $setPropertyValue,
        private readonly SetSnapshot $setSnapshot,
        private readonly GetPropertyValue $getPropertyValue
    ) {
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @return void
     */
    public function __invoke(object $entity, array $data): void
    {
        // Set all properties via setters
        foreach ($data as $columnName => $value) {
            ($this->setPropertyValue)($entity, $columnName, $value);
        }

        // Build snapshot from typed property values (not raw data)
        $snapshot = [];
        foreach ($data as $columnName => $value) {
            $snapshot[$columnName] = ($this->getPropertyValue)($entity, $columnName);
        }

        // Set snapshot after hydration
        ($this->setSnapshot)($entity, $snapshot);
    }
}
