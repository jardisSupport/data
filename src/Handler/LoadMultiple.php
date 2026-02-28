<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Loads multiple database rows into an array of entities.
 *
 * Uses a template entity and clones it for each row.
 */
class LoadMultiple
{
    public function __construct(
        private readonly CloneEntity $cloneEntity,
        private readonly HydrateEntity $hydrateEntity
    ) {
    }

    /**
     * Load multiple rows into entities.
     *
     * @param object $template Template entity to clone for each row
     * @param array<int, array<string, mixed>> $rows Array of database rows
     * @return array<int, object> Array of hydrated entities
     * @throws \ReflectionException
     */
    public function __invoke(object $template, array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $entities = [];

        foreach ($rows as $row) {
            $entity = ($this->cloneEntity)($template);
            ($this->hydrateEntity)($entity, $row);
            $entities[] = $entity;
        }

        return $entities;
    }
}
