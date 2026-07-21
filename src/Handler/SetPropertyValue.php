<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Sets a property value via setter method if available, otherwise via reflection.
 *
 * Supports optional type casting for automatic conversion from database values.
 * Caches ReflectionClass instances per entity class for performance.
 */
class SetPropertyValue
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

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

        $reflection = $this->getReflection($entity);

        $property = null;
        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
        }

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

    /**
     * @param object $entity
     * @return ReflectionClass<object>
     */
    private function getReflection(object $entity): ReflectionClass
    {
        $class = get_class($entity);
        return $this->reflectionCache[$class] ??= new ReflectionClass($entity);
    }
}
