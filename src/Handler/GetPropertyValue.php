<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionProperty;

/**
 * Gets a property value via getter method if available, otherwise via reflection.
 *
 * Uses property-level caching for ReflectionProperty instances to avoid
 * expensive reflection operations on repeated calls.
 */
class GetPropertyValue
{
    /**
     * Cache of ReflectionProperty instances by class and property name.
     *
     * @var array<string, ReflectionProperty|null>
     */
    private array $propertyCache = [];

    public function __construct(
        private readonly ColumnNameToPropertyName $columnNameToPropertyName
    ) {
    }

    /**
     * @param object $entity
     * @param string $columnName
     * @return mixed
     */
    public function __invoke(object $entity, string $columnName): mixed
    {
        $propertyName = ($this->columnNameToPropertyName)($columnName);
        $getterMethod = 'get' . ucfirst($propertyName);

        // Try getter method first (fast path)
        if (method_exists($entity, $getterMethod)) {
            return $entity->$getterMethod();
        }

        // Try is*() getter (boolean properties)
        $isMethod = 'is' . ucfirst($propertyName);
        if (method_exists($entity, $isMethod)) {
            return $entity->$isMethod();
        }

        // Try has*() getter (boolean properties)
        $hasMethod = 'has' . ucfirst($propertyName);
        if (method_exists($entity, $hasMethod)) {
            return $entity->$hasMethod();
        }

        // Fallback: Direct property access via cached reflection
        $className = get_class($entity);
        $cacheKey = $className . '::' . $propertyName;

        // Cache ReflectionProperty for reuse
        if (!isset($this->propertyCache[$cacheKey])) {
            $reflection = new ReflectionClass($entity);

            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $this->propertyCache[$cacheKey] = $property;
            } else {
                // Cache negative result to avoid repeated checks
                $this->propertyCache[$cacheKey] = null;
            }
        }

        $property = $this->propertyCache[$cacheKey];
        if ($property !== null) {
            return $property->getValue($entity);
        }

        return null;
    }
}
