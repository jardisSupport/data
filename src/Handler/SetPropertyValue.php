<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionProperty;

/**
 * Sets a property value via setter method if available, otherwise via reflection.
 *
 * Uses property-level caching for ReflectionProperty instances to avoid
 * expensive reflection operations on repeated calls.
 *
 * Supports optional type casting for automatic conversion from database values.
 */
class SetPropertyValue
{
    /**
     * Cache of ReflectionProperty instances by class and property name.
     *
     * @var array<string, ReflectionProperty|null>
     */
    private array $propertyCache = [];

    public function __construct(
        private readonly ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly ?TypeCaster $typeCaster = null
    ) {
    }

    /**
     * @param object $entity
     * @param string $columnName
     * @param mixed $value
     * @return void
     */
    public function __invoke(object $entity, string $columnName, mixed $value): void
    {
        $propertyName = ($this->columnNameToPropertyName)($columnName);
        $className = get_class($entity);
        $cacheKey = $className . '::' . $propertyName;

        // Get or cache property
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

        // Type cast value if TypeCaster is available
        if ($this->typeCaster !== null && $property !== null) {
            $value = ($this->typeCaster)($value, $property);
        }

        // Try setter method first (fast path)
        $setterMethod = 'set' . ucfirst($propertyName);
        if (method_exists($entity, $setterMethod)) {
            $entity->$setterMethod($value);
            return;
        }

        // Fallback: Direct property access
        if ($property !== null) {
            $property->setValue($entity, $value);
        }
    }
}
