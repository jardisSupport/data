<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionProperty;

/**
 * Gets the snapshot from an entity.
 * Uses getSnapshot() method if available for performance.
 * Caches ReflectionProperty for entities without getSnapshot() method.
 */
class GetSnapshot
{
    /**
     * Cache of __snapshot ReflectionProperty by class name.
     *
     * @var array<string, ReflectionProperty|false>
     */
    private array $propertyCache = [];

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    public function __invoke(object $entity): array
    {
        // Fast path: Use getSnapshot() method if available
        if (method_exists($entity, 'getSnapshot')) {
            return $entity->getSnapshot();
        }

        // Fallback: Reflection with caching
        $className = get_class($entity);

        // Cache ReflectionProperty for __snapshot
        if (!isset($this->propertyCache[$className])) {
            $reflection = new ReflectionClass($entity);

            if ($reflection->hasProperty('__snapshot')) {
                $property = $reflection->getProperty('__snapshot');
                $property->setAccessible(true);
                $this->propertyCache[$className] = $property;
            } else {
                // Cache negative result
                $this->propertyCache[$className] = false;
            }
        }

        $property = $this->propertyCache[$className];
        if ($property instanceof ReflectionProperty) {
            return $property->getValue($entity) ?? [];
        }

        return [];
    }
}
