<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionException;

/**
 * Recursively deep-clones an aggregate (full object graph).
 *
 * Copies all properties including relations. Nested objects and arrays of objects
 * are cloned recursively. Snapshot is copied at every level.
 *
 * Stateless — no internal caches.
 */
class CloneAggregate
{
    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly SetSnapshot $setSnapshot
    ) {
    }

    /**
     * Creates a deep clone of an aggregate.
     *
     * @param object $entity The aggregate to clone
     * @return object The cloned aggregate
     * @throws ReflectionException
     */
    public function __invoke(object $entity): object
    {
        return $this->deepCloneObject($entity);
    }

    /**
     * Deep clone an object recursively.
     *
     * @param object $object
     * @return object
     */
    private function deepCloneObject(object $object): object
    {
        $reflection = new ReflectionClass($object);
        $clone = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            if ($property->getName() === '__snapshot') {
                continue;
            }

            if (!$property->isInitialized($object)) {
                continue;
            }

            $value = $property->getValue($object);
            $property->setValue($clone, $this->deepCloneValue($value));
        }

        // Copy snapshot
        $snapshot = ($this->getSnapshot)($object);
        if (!empty($snapshot)) {
            ($this->setSnapshot)($clone, $snapshot);
        }

        return $clone;
    }

    /**
     * Deep clone a value recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    private function deepCloneValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->deepCloneArray($value);
        }

        if (is_object($value)) {
            if ($value instanceof \UnitEnum) {
                return $value;
            }
            if ($value instanceof \DateTimeInterface) {
                return clone $value;
            }
            return $this->deepCloneObject($value);
        }

        return $value;
    }

    /**
     * Deep clone an array recursively.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function deepCloneArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $item) {
            $result[$key] = $this->deepCloneValue($item);
        }

        return $result;
    }
}
