<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use DateTimeInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Clones an entity at entity-level only (flat clone).
 *
 * Uses snapshot as source of truth: only properties listed in the snapshot
 * are copied. Snapshot is copied from source. Stateless — no internal caches.
 */
class CloneEntity
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly SetSnapshot $setSnapshot,
        private readonly ColumnNameToPropertyName $columnNameToPropertyName
    ) {
    }

    /**
     * Creates a flat clone of an entity (DB-column properties only).
     *
     * @param object $entity The entity to clone
     * @return object The cloned entity
     * @throws ReflectionException
     */
    public function __invoke(object $entity): object
    {
        $snapshot = ($this->getSnapshot)($entity);
        $dbPropertyNames = $this->resolveDbPropertyNames($snapshot);

        $reflection = new ReflectionClass($entity);
        $clone = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            if (!isset($dbPropertyNames[$property->getName()])) {
                continue;
            }

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $value = $property->getValue($entity);

            if ($value instanceof DateTimeInterface) {
                $value = clone $value;
            }

            $property->setValue($clone, $value);
        }

        if (!empty($snapshot)) {
            ($this->setSnapshot)($clone, $snapshot);
        }

        return $clone;
    }

    /**
     * Build a set of DB property names from snapshot keys.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, true>
     */
    private function resolveDbPropertyNames(array $snapshot): array
    {
        $names = [];
        foreach (array_keys($snapshot) as $columnName) {
            $names[($this->columnNameToPropertyName)($columnName)] = true;
        }
        return $names;
    }
}
