<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Detects changes between current entity state and snapshot.
 */
class DetectChanges
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly GetPropertyValue $getPropertyValue
    ) {
    }

    /**
     * Returns array of changed fields with their new values.
     *
     * @param object $entity
     * @return array<string, mixed>
     */
    public function __invoke(object $entity): array
    {
        $snapshot = ($this->getSnapshot)($entity);

        if (empty($snapshot)) {
            return [];
        }

        $changes = [];

        foreach ($snapshot as $columnName => $originalValue) {
            $currentValue = ($this->getPropertyValue)($entity, $columnName);

            // Detect change (strict comparison)
            if ($currentValue !== $originalValue) {
                $changes[$columnName] = $currentValue;
            }
        }

        return $changes;
    }
}
