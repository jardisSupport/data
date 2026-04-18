<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Gets a property value via getter method if available, otherwise via reflection.
 *
 * Caches ReflectionClass instances per entity class for performance.
 */
class GetPropertyValue
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

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

        // Fallback: Direct property access via reflection
        $class = get_class($entity);
        $reflection = $this->reflectionCache[$class] ??= new ReflectionClass($entity);

        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
            return $property->getValue($entity);
        }

        return null;
    }
}
