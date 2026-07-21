<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;

/**
 * Converts an entity to a flat associative array (entity-level only).
 *
 * Uses snapshot as source of truth: only properties whose column names
 * appear in the snapshot are included. Stateless — no internal caches.
 */
class EntityToArray
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly ColumnNameToPropertyName $columnNameToPropertyName
    ) {
    }

    /**
     * Convert entity to flat array (entity-level, no relations).
     *
     * @param object $entity The entity to convert
     * @return array<string, mixed> Associative array of column names to formatted values
     */
    public function __invoke(object $entity): array
    {
        $snapshot = ($this->getSnapshot)($entity);
        $propertyToColumn = $this->buildPropertyToColumnMap($snapshot);

        $reflection = new ReflectionClass($entity);
        $result = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            if (!isset($propertyToColumn[$propertyName])) {
                continue;
            }

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $result[$propertyToColumn[$propertyName]] = $this->formatValue($property->getValue($entity));
        }

        return $result;
    }

    /**
     * Build a map from property names to column names using snapshot keys.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, string>
     */
    private function buildPropertyToColumnMap(array $snapshot): array
    {
        $map = [];
        foreach (array_keys($snapshot) as $columnName) {
            $map[($this->columnNameToPropertyName)($columnName)] = (string) $columnName;
        }
        return $map;
    }

    /**
     * Format value for output.
     *
     * @param mixed $value
     * @return mixed
     */
    private function formatValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        return $value;
    }

    /**
     * Format array values.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function formatArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $item) {
            $result[$key] = $this->formatValue($item);
        }

        return $result;
    }
}
