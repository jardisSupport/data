<?php

declare(strict_types=1);

namespace JardisSupport\Data;

use ReflectionException;

/**
 * Interface for managing data entities with reflection-based operations.
 *
 * Provides hydration from database rows and change tracking via snapshots.
 */
interface DataServiceInterface
{
    /**
     * Hydrates an entity with data from a database.
     *
     * Sets both the entity properties (via setters) and the __snapshot
     * for change tracking.
     *
     * @template T of object
     * @param T $entity
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrate(object $entity, array $data): object;

    /**
     * Hydrates an aggregate with nested data structures.
     *
     * Recursively hydrates nested objects and arrays of objects.
     * Sets both the aggregate properties and snapshots for change tracking.
     *
     * @template T of object
     * @param T $aggregate
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrateFromArray(object $aggregate, array $data): object;

    /**
     * Detects changes between current entity state and snapshot.
     *
     * Compares current property values with the snapshot taken during hydration.
     *
     * @param object $entity The entity to check for changes
     * @return array<string, mixed> Map of changed field names to their new values
     */
    public function getChanges(object $entity): array;

    /**
     * Gets the snapshot from an entity.
     *
     * @param object $entity
     * @return array<string, mixed>
     */
    public function getSnapshot(object $entity): array;

    /**
     * Checks if an entity has any changes.
     *
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool;

    /**
     * Gets only the changed field names (without old/new values).
     *
     * @param object $entity
     * @return array<int, string>
     */
    public function getChangedFields(object $entity): array;

    /**
     * Creates a deep clone of an entity including all properties and snapshot.
     *
     * @param object $entity The entity to clone
     * @return object The cloned entity
     * @throws ReflectionException
     */
    public function clone(object $entity): object;

    /**
     * Compares two entities of the same class and returns differences.
     *
     * @param object $entity1 First entity (reference)
     * @param object $entity2 Second entity (compare against)
     * @return array<string, mixed> Map of property names to values from entity2 that differ
     */
    public function diff(object $entity1, object $entity2): array;

    /**
     * Converts an entity to an associative array.
     *
     * Formats DateTime properties according to their type hints.
     *
     * @param object $entity The entity to convert
     * @return array<string, mixed> Associative array of property values
     */
    public function toArray(object $entity): array;

    /**
     * Loads multiple database rows into an array of entities.
     *
     * @param object $template Template entity to clone for each row
     * @param array<int, array<string, mixed>> $rows Array of database rows
     * @return array<int, object> Array of hydrated entities
     */
    public function loadMultiple(object $template, array $rows): array;

    /**
     * Updates entity properties without modifying the snapshot.
     *
     * Useful for applying changes while preserving change tracking.
     *
     * @param object $entity The entity to update
     * @param array<string, mixed> $data Property values to update
     * @return void
     */
    public function updateProperties(object $entity, array $data): void;

    /**
     * Generates a UUID v4 (random).
     *
     * @return string UUID v4 in canonical format
     */
    public function generateUuid4(): string;

    /**
     * Generates a UUID v7 (time-ordered).
     *
     * UUIDs are lexicographically sortable by creation time.
     *
     * @return string UUID v7 in canonical format
     */
    public function generateUuid7(): string;
}
