<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionException;

/**
 * Clones an entity including all properties and snapshot.
 * Creates a deep copy with both property values and snapshot preserved.
 *
 * Handles nested objects and arrays recursively:
 * - Nested objects → deep cloned recursively
 * - Arrays of objects → each element deep cloned
 * - Scalar values → copied as-is
 *
 * Performance: Caches properties per class to avoid repeated getProperties() calls.
 */
class CloneEntity
{
    /**
     * Cache of ReflectionClass instances by class name.
     *
     * @var array<string, \ReflectionClass<object>>
     */
    private array $reflectionCache = [];

    /**
     * Cache of properties per class (already setAccessible, excluding __snapshot).
     *
     * @var array<string, array<int, \ReflectionProperty>>
     */
    private array $propertiesCache = [];

    public function __construct(
        private readonly GetSnapshot $getSnapshot,
        private readonly SetSnapshot $setSnapshot
    ) {
    }

    /**
     * Creates a deep clone of an entity.
     *
     * @param object $entity The entity to clone
     * @return object The cloned entity
     * @throws ReflectionException
     */
    public function __invoke(object $entity): object
    {
        $className = get_class($entity);

        // Get or cache reflection
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new ReflectionClass($entity);
        }

        // Get or cache properties
        if (!isset($this->propertiesCache[$className])) {
            $this->cacheClassProperties($className);
        }

        $reflection = $this->reflectionCache[$className];
        $properties = $this->propertiesCache[$className];

        // Create new instance without calling constructor
        $clone = $reflection->newInstanceWithoutConstructor();

        // Copy all properties using cached property list
        foreach ($properties as $property) {
            if ($property->isInitialized($entity)) {
                $value = $property->getValue($entity);

                // Deep clone the value (handles objects, arrays, and scalars)
                $value = $this->deepCloneValue($value);

                $property->setValue($clone, $value);
            }
        }

        // Copy snapshot
        $snapshot = ($this->getSnapshot)($entity);
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
        // Null and scalar values
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        // Arrays (may contain objects)
        if (is_array($value)) {
            return $this->deepCloneArray($value);
        }

        // Objects — recursively deep clone (except built-in value objects)
        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface || $value instanceof \UnitEnum) {
                return clone $value;
            }
            return $this->deepCloneObject($value);
        }

        return $value;
    }

    /**
     * Deep clone an object recursively.
     *
     * @param object $object
     * @return object
     */
    private function deepCloneObject(object $object): object
    {
        $className = get_class($object);

        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new ReflectionClass($object);
        }

        if (!isset($this->propertiesCache[$className])) {
            $this->cacheClassProperties($className);
        }

        $reflection = $this->reflectionCache[$className];
        $properties = $this->propertiesCache[$className];

        $clone = $reflection->newInstanceWithoutConstructor();

        foreach ($properties as $property) {
            if ($property->isInitialized($object)) {
                $value = $property->getValue($object);
                $property->setValue($clone, $this->deepCloneValue($value));
            }
        }

        // Copy snapshot for nested objects (same as top-level in __invoke)
        $snapshot = ($this->getSnapshot)($object);
        if (!empty($snapshot)) {
            ($this->setSnapshot)($clone, $snapshot);
        }

        return $clone;
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

    /**
     * Cache properties for a class (excluding __snapshot, already setAccessible).
     *
     * @param string $className
     * @return void
     */
    private function cacheClassProperties(string $className): void
    {
        $reflection = $this->reflectionCache[$className];
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            // Skip __snapshot property (handled separately)
            if ($property->getName() === '__snapshot') {
                continue;
            }

            $property->setAccessible(true);
            $properties[] = $property;
        }

        $this->propertiesCache[$className] = $properties;
    }
}
