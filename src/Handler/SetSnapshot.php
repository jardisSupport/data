<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Sets the __snapshot property via reflection.
 *
 * Caches ReflectionClass instances per entity class for performance.
 */
class SetSnapshot
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @return void
     */
    public function __invoke(object $entity, array $data): void
    {
        $class = get_class($entity);
        $reflection = $this->reflectionCache[$class] ??= new ReflectionClass($entity);

        if ($reflection->hasProperty('__snapshot')) {
            $property = $reflection->getProperty('__snapshot');
            $property->setValue($entity, $data);
        }
    }
}
