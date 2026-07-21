<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Gets the snapshot from an entity.
 *
 * Uses getSnapshot() method if available for performance.
 * Caches ReflectionClass instances per entity class for performance.
 */
class GetSnapshot
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

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

        // Fallback: Reflection
        $class = get_class($entity);
        $reflection = $this->reflectionCache[$class] ??= new ReflectionClass($entity);

        if ($reflection->hasProperty('__snapshot')) {
            $property = $reflection->getProperty('__snapshot');
            return $property->getValue($entity) ?? [];
        }

        return [];
    }
}
