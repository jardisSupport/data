<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;

/**
 * Converts an aggregate to a nested associative array (full object graph).
 *
 * Uses __snapshot keys as source of truth for DB column names.
 * Scalar properties use snapshot column names as keys,
 * relation properties (objects, arrays of objects) keep their property names.
 *
 * Stateless — no internal caches.
 */
class AggregateToArray
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly ColumnNameToPropertyName $columnNameToPropertyName
    ) {
    }

    /**
     * Convert aggregate to nested array (full object graph).
     *
     * @param object $entity The aggregate to convert
     * @return array<string, mixed> Nested associative array of the full object graph
     */
    public function __invoke(object $entity): array
    {
        return $this->convertEntity($entity);
    }

    /**
     * Convert a single entity using its snapshot for column name resolution.
     *
     * @param object $entity
     * @return array<string, mixed>
     */
    private function convertEntity(object $entity): array
    {
        $snapshot = ($this->getSnapshot)($entity);
        $propertyToColumn = $this->buildPropertyToColumnMap($snapshot);

        $reflection = new ReflectionClass($entity);
        $result = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            if ($propertyName === '__snapshot') {
                continue;
            }

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $value = $property->getValue($entity);

            // DB column property (in snapshot) → column name as key
            if (isset($propertyToColumn[$propertyName])) {
                $result[$propertyToColumn[$propertyName]] = $this->formatScalar($value);
                continue;
            }

            // Relation/other property → property name as key
            $result[$propertyName] = $this->formatValue($value);
        }

        return $result;
    }

    /**
     * Build a map from property names to column names using snapshot keys.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, string> propertyName => columnName
     */
    private function buildPropertyToColumnMap(array $snapshot): array
    {
        $map = [];
        foreach (array_keys($snapshot) as $columnName) {
            $map[($this->columnNameToPropertyName)((string) $columnName)] = (string) $columnName;
        }
        return $map;
    }

    /**
     * Format a scalar value for output.
     *
     * @param mixed $value
     * @return mixed
     */
    private function formatScalar(mixed $value): mixed
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

        return $value;
    }

    /**
     * Format a relation or non-snapshot value.
     *
     * @param mixed $value
     * @return mixed
     */
    private function formatValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value)) {
            return $this->convertEntity($value);
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        return $this->formatScalar($value);
    }

    /**
     * Format array values (recursively handle nested objects).
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function formatArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $item) {
            if (is_object($item)) {
                $result[$key] = $this->convertEntity($item);
            } elseif (is_array($item)) {
                $result[$key] = $this->formatArray($item);
            } else {
                $result[$key] = $this->formatScalar($item);
            }
        }

        return $result;
    }
}
