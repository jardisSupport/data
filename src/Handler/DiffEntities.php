<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use DateTimeInterface;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Compares two entities of the same class and returns their differences.
 *
 * Uses snapshot as source of truth: only properties listed in the union
 * of both entities' snapshots are compared. Stateless — no internal caches.
 */
class DiffEntities
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly ToSnapshotValue $toSnapshotValue = new ToSnapshotValue()
    ) {
    }

    /**
     * Compare two entities and return differences.
     *
     * @param object $entity1 First entity (reference)
     * @param object $entity2 Second entity (compare against)
     * @return array<string, mixed> Map of column names to values from entity2 that differ from entity1
     */
    public function __invoke(object $entity1, object $entity2): array
    {
        $class1 = get_class($entity1);
        $class2 = get_class($entity2);

        if ($class1 !== $class2) {
            throw new InvalidArgumentException(
                "Cannot diff entities of different classes: $class1 vs $class2"
            );
        }

        $propertyToColumn = $this->buildPropertyToColumnMap($entity1, $entity2);

        $reflection = new ReflectionClass($entity1);
        $differences = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            if (!isset($propertyToColumn[$propertyName])) {
                continue;
            }

            $columnName = $propertyToColumn[$propertyName];

            if (!$property->isInitialized($entity1) && !$property->isInitialized($entity2)) {
                continue;
            }

            if ($property->isInitialized($entity1) !== $property->isInitialized($entity2)) {
                $differences[$columnName] = $property->isInitialized($entity2)
                    ? ($this->toSnapshotValue)($property->getValue($entity2))
                    : null;
                continue;
            }

            $value1 = $property->getValue($entity1);
            $value2 = $property->getValue($entity2);

            if (!$this->valuesAreEqual($value1, $value2)) {
                $differences[$columnName] = ($this->toSnapshotValue)($value2);
            }
        }

        return $differences;
    }

    /**
     * Build a map from property names to column names using snapshot keys.
     *
     * @param object $entity1
     * @param object $entity2
     * @return array<string, string>
     */
    private function buildPropertyToColumnMap(object $entity1, object $entity2): array
    {
        $snapshot1 = ($this->getSnapshot)($entity1);
        $snapshot2 = ($this->getSnapshot)($entity2);

        $map = [];
        foreach (array_keys($snapshot1 + $snapshot2) as $columnName) {
            $map[($this->columnNameToPropertyName)((string) $columnName)] = (string) $columnName;
        }
        return $map;
    }

    /**
     * Compare two values for equality.
     *
     * @param mixed $value1
     * @param mixed $value2
     * @return bool True if values are equal
     */
    private function valuesAreEqual(mixed $value1, mixed $value2): bool
    {
        if ($value1 === $value2) {
            return true;
        }

        if ($value1 instanceof DateTimeInterface && $value2 instanceof DateTimeInterface) {
            return $value1->getTimestamp() === $value2->getTimestamp();
        }

        return false;
    }
}
