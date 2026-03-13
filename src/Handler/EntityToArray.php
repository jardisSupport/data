<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use DateTimeInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Converts an entity to an associative array.
 *
 * Formats values based on their types:
 * - DateTime/DateTimeImmutable → 'Y-m-d H:i:s' format
 * - Nested objects → recursively converted to arrays
 * - Arrays of objects → each element converted to array
 * - Other scalar types → as-is
 * - Skips __snapshot property
 *
 * Performance: Caches properties and type names per class.
 */
class EntityToArray
{
    /** @var array<string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /**
     * Cache of properties per class (already setAccessible).
     *
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $propertiesCache = [];

    /**
     * Convert entity to array.
     *
     * @param object $entity The entity to convert
     * @return array<string, mixed> Associative array of property names and formatted values
     */
    public function __invoke(object $entity): array
    {
        $className = get_class($entity);

        if (!isset($this->propertiesCache[$className])) {
            $this->cacheClassProperties($className);
        }

        $properties = $this->propertiesCache[$className];
        $result = [];

        foreach ($properties as $propertyName => $property) {
            // Skip uninitialized properties
            if (!$property->isInitialized($entity)) {
                continue;
            }

            $value = $property->getValue($entity);
            $result[$propertyName] = $this->formatValue($value, $property);
        }

        return $result;
    }

    /**
     * Cache all properties for a class (excluding __snapshot).
     *
     * @param class-string $className
     * @return void
     * @throws ReflectionException
     */
    private function cacheClassProperties(string $className): void
    {
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new ReflectionClass($className);
        }

        $reflection = $this->reflectionCache[$className];
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            // Skip snapshot property
            if ($propertyName === '__snapshot') {
                continue;
            }

            $property->setAccessible(true);
            $properties[$propertyName] = $property;
        }

        $this->propertiesCache[$className] = $properties;
    }

    /**
     * Format value based on type.
     *
     * @param mixed $value
     * @param ReflectionProperty $property
     * @return mixed
     * @throws ReflectionException
     */
    private function formatValue(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle DateTime/DateTimeImmutable formatting
        if ($value instanceof DateTimeInterface) {
            return $this->formatDateTime($value);
        }

        // Handle arrays (possibly containing objects)
        if (is_array($value)) {
            return $this->formatArray($value);
        }

        // Handle nested objects (recursively convert to array)
        if (is_object($value)) {
            return $this->convertObjectToArray($value);
        }

        // Return scalar values as-is
        return $value;
    }

    /**
     * Format array values (recursively handle nested objects).
     *
     * @param array<mixed> $array
     * @return array<mixed>
     * @throws ReflectionException
     */
    private function formatArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $item) {
            if ($item === null) {
                $result[$key] = null;
            } elseif ($item instanceof DateTimeInterface) {
                // Format DateTime/DateTimeImmutable (use default format)
                $result[$key] = $item->format('Y-m-d H:i:s');
            } elseif (is_object($item)) {
                $result[$key] = $this->convertObjectToArray($item);
            } elseif (is_array($item)) {
                $result[$key] = $this->formatArray($item);
            } else {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Convert nested object to array (recursively).
     *
     * @param object $object
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private function convertObjectToArray(object $object): array
    {
        $className = get_class($object);

        if (!isset($this->propertiesCache[$className])) {
            $this->cacheClassProperties($className);
        }

        $properties = $this->propertiesCache[$className];
        $result = [];

        foreach ($properties as $propertyName => $property) {
            // Skip uninitialized properties
            if (!$property->isInitialized($object)) {
                continue;
            }

            $value = $property->getValue($object);
            $result[$propertyName] = $this->formatValue($value, $property);
        }

        return $result;
    }

    /**
     * Format DateTime/DateTimeImmutable to standard format.
     *
     * @param DateTimeInterface $dateTime
     * @return string
     */
    private function formatDateTime(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
}
