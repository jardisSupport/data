<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionProperty;

/**
 * Sets the __snapshot property via reflection.
 * Caches ReflectionProperty for better performance on repeated calls.
 */
class SetSnapshot
{
    /**
     * Cache of __snapshot ReflectionProperty by class name.
     *
     * @var array<string, ReflectionProperty|false>
     */
    private array $propertyCache = [];

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @return void
     */
    public function __invoke(object $entity, array $data): void
    {
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
            $property->setValue($entity, $data);
        }
    }
}
