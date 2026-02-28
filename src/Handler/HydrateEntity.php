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
        private readonly SetSnapshot $setSnapshot
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

        // Set snapshot after hydration
        ($this->setSnapshot)($entity, $data);
    }
}
