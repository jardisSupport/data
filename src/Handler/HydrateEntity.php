<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Hydrates an entity with data from database.
 *
 * Uses value-based detection for scalars/DateTime/BackedEnum. For arrays,
 * checks the property type: only sets if the property is typed as 'array'
 * (decision in IsHydratableColumn).
 * Snapshot stores scalar representations only (DateTime → string, BackedEnum → value).
 */
class HydrateEntity
{
    private readonly IsHydratableColumn $isHydratableColumn;

    public function __construct(
        private readonly SetPropertyValue $setPropertyValue,
        private readonly SetSnapshot $setSnapshot,
        private readonly GetSnapshot $getSnapshot,
        private readonly GetPropertyValue $getPropertyValue,
        ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly ToSnapshotValue $toSnapshotValue = new ToSnapshotValue(),
        ?IsHydratableColumn $isHydratableColumn = null
    ) {
        $this->isHydratableColumn = $isHydratableColumn ?? new IsHydratableColumn($columnNameToPropertyName);
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @param bool $updateSnapshot Whether to update the snapshot (false for apply)
     * @return void
     */
    public function __invoke(object $entity, array $data, bool $updateSnapshot = true): void
    {
        $reflection = new ReflectionClass($entity);

        $snapshot = $updateSnapshot ? ($this->getSnapshot)($entity) : null;
        foreach ($data as $columnName => $value) {
            if (!($this->isHydratableColumn)($value, $columnName, $reflection)) {
                continue;
            }

            ($this->setPropertyValue)($entity, $columnName, $value);

            if ($snapshot !== null) {
                $typedValue = ($this->getPropertyValue)($entity, $columnName);
                $snapshot[$columnName] = ($this->toSnapshotValue)($typedValue);
            }
        }

        if ($snapshot !== null) {
            ($this->setSnapshot)($entity, $snapshot);
        }
    }
}
