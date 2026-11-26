<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Merges column values into the snapshot of an entity without touching its properties.
 *
 * Each column passes the same filter as hydration (IsHydratableColumn), is type-cast
 * against its property (TypeCaster) and stored in scalar snapshot form (ToSnapshotValue),
 * so the snapshot equals the one hydration builds for the same data. Columns not in
 * $data keep their snapshot value. A column without a matching property is recorded
 * as null, as hydration records it.
 */
class MergeSnapshot
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly SetSnapshot $setSnapshot,
        private readonly IsHydratableColumn $isHydratableColumn,
        private readonly ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly TypeCaster $typeCaster,
        private readonly ToSnapshotValue $toSnapshotValue
    ) {
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $data Column name => value
     * @return void
     */
    public function __invoke(object $entity, array $data): void
    {
        $reflection = new ReflectionClass($entity);
        $snapshot = ($this->getSnapshot)($entity);

        foreach ($data as $columnName => $value) {
            if (!($this->isHydratableColumn)($value, $columnName, $reflection)) {
                continue;
            }

            $propertyName = ($this->columnNameToPropertyName)($columnName);
            $typedValue = $reflection->hasProperty($propertyName)
                ? ($this->typeCaster)($value, $reflection->getProperty($propertyName))
                : null;

            $snapshot[$columnName] = ($this->toSnapshotValue)($typedValue);
        }

        ($this->setSnapshot)($entity, $snapshot);
    }
}
