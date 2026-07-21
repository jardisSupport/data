<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Detects changes between current entity state and snapshot.
 *
 * Snapshot contains scalar representations (DateTime as string, BackedEnum as value).
 * Current property values are converted to the same representation before comparison.
 */
class DetectChanges
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly GetPropertyValue $getPropertyValue,
        private readonly ToSnapshotValue $toSnapshotValue = new ToSnapshotValue()
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
            $currentValue = ($this->getPropertyValue)($entity, (string) $columnName);
            $comparableValue = ($this->toSnapshotValue)($currentValue);

            if ($comparableValue !== $originalValue) {
                $changes[$columnName] = $comparableValue;
            }
        }

        return $changes;
    }
}
