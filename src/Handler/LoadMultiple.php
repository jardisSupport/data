<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;

/**
 * Loads multiple database rows into an array of entities.
 *
 * Creates fresh instances via reflection and hydrates each from its row.
 */
class LoadMultiple
{
    public function __construct(
        private readonly HydrateEntity $hydrateEntity
    ) {
    }

    /**
     * Load multiple rows into entities.
     *
     * @param object $template Template entity (used for class resolution only)
     * @param array<int, array<string, mixed>> $rows Array of database rows
     * @return array<int, object> Array of hydrated entities
     * @throws \ReflectionException
     */
    public function __invoke(object $template, array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $reflection = new ReflectionClass($template);
        $entities = [];

        foreach ($rows as $row) {
            $entity = $reflection->newInstanceWithoutConstructor();
            ($this->hydrateEntity)($entity, $row);
            $entities[] = $entity;
        }

        return $entities;
    }
}
