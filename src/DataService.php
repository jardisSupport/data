<?php

declare(strict_types=1);

namespace JardisSupport\Data;

use JardisSupport\Data\Handler\CloneEntity;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\DetectChanges;
use JardisSupport\Data\Handler\DiffEntities;
use JardisSupport\Data\Handler\EntityToArray;
use JardisSupport\Data\Handler\GenerateUuid4;
use JardisSupport\Data\Handler\GenerateUuid7;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\HydrateEntity;
use JardisSupport\Data\Handler\LoadMultiple;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Handler\UpdateProperties;
use ReflectionException;

/**
 * Service for managing data entities with reflection-based operations.
 *
 * Provides hydration from database rows and change tracking via snapshots.
 * Uses handler architecture similar to ModelExporter for extensibility.
 */
class DataService implements DataServiceInterface
{
    private ?HydrateEntity $hydrateEntity = null;
    private ?HydrateAggregate $hydrateAggregate = null;
    private ?DetectChanges $detectChanges = null;
    private ?GetSnapshot $getSnapshot = null;
    private ?CloneEntity $cloneEntity = null;
    private ?DiffEntities $diffEntities = null;
    private ?EntityToArray $entityToArray = null;
    private ?LoadMultiple $loadMultiple = null;
    private ?UpdateProperties $updateProperties = null;
    private ?GenerateUuid4 $generateUuid4 = null;
    private ?GenerateUuid7 $generateUuid7 = null;

    // Cached shared helper instances
    private ?ColumnNameToPropertyName $columnNameToPropertyName = null;
    private ?SetPropertyValue $setPropertyValue = null;
    private ?GetPropertyValue $getPropertyValue = null;
    private ?SetSnapshot $setSnapshot = null;
    private ?TypeCaster $typeCaster = null;

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
    public function hydrate(object $entity, array $data): object
    {
        ($this->getHydrateEntity())($entity, $data);
        return $entity;
    }

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
    public function hydrateFromArray(object $aggregate, array $data): object
    {
        ($this->getHydrateAggregate())($aggregate, $data);
        return $aggregate;
    }

    /**
     * Detects changes between current entity state and snapshot.
     *
     * Compares current property values with the snapshot taken during hydration.
     *
     * @param object $entity The entity to check for changes
     * @return array<string, mixed> Map of changed field names to their new values
     */
    public function getChanges(object $entity): array
    {
        return ($this->getDetectChanges())($entity);
    }

    /**
     * Gets the snapshot from an entity.
     *
     * @param object $entity
     * @return array<string, mixed>
     */
    public function getSnapshot(object $entity): array
    {
        return ($this->getGetSnapshot())($entity);
    }

    /**
     * Checks if an entity has any changes.
     *
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool
    {
        return !empty($this->getChanges($entity));
    }

    /**
     * Gets only the changed field names (without old/new values).
     *
     * @param object $entity
     * @return array<int, string>
     */
    public function getChangedFields(object $entity): array
    {
        return array_keys($this->getChanges($entity));
    }

    /**
     * Creates a deep clone of an entity including all properties and snapshot.
     *
     * @param object $entity The entity to clone
     * @return object The cloned entity
     * @throws ReflectionException
     */
    public function clone(object $entity): object
    {
        return ($this->getCloneEntity())($entity);
    }

    /**
     * Compares two entities of the same class and returns differences.
     *
     * @param object $entity1 First entity (reference)
     * @param object $entity2 Second entity (compare against)
     * @return array<string, mixed> Map of property names to values from entity2 that differ
     */
    public function diff(object $entity1, object $entity2): array
    {
        return ($this->getDiffEntities())($entity1, $entity2);
    }

    /**
     * Converts an entity to an associative array.
     *
     * Formats DateTime properties according to their type hints.
     *
     * @param object $entity The entity to convert
     * @return array<string, mixed> Associative array of property values
     */
    public function toArray(object $entity): array
    {
        return ($this->getEntityToArray())($entity);
    }

    /**
     * Loads multiple database rows into an array of entities.
     *
     * @param object $template Template entity to clone for each row
     * @param array<int, array<string, mixed>> $rows Array of database rows
     * @return array<int, object> Array of hydrated entities
     */
    public function loadMultiple(object $template, array $rows): array
    {
        return ($this->getLoadMultiple())($template, $rows);
    }

    /**
     * Updates entity properties without modifying the snapshot.
     *
     * Useful for applying changes while preserving change tracking.
     *
     * @param object $entity The entity to update
     * @param array<string, mixed> $data Property values to update
     * @return void
     */
    public function updateProperties(object $entity, array $data): void
    {
        ($this->getUpdateProperties())($entity, $data);
    }

    /**
     * Generates a UUID v4 (random).
     *
     * @return string UUID v4 in canonical format
     */
    public function generateUuid4(): string
    {
        return ($this->getGenerateUuid4())();
    }

    /**
     * Generates a UUID v7 (time-ordered).
     *
     * UUIDs are lexicographically sortable by creation time.
     *
     * @return string UUID v7 in canonical format
     */
    public function generateUuid7(): string
    {
        return ($this->getGenerateUuid7())();
    }

    private function getColumnNameToPropertyName(): ColumnNameToPropertyName
    {
        return $this->columnNameToPropertyName ??= new ColumnNameToPropertyName();
    }

    private function getTypeCaster(): TypeCaster
    {
        return $this->typeCaster ??= new TypeCaster();
    }

    private function getSetPropertyValue(): SetPropertyValue
    {
        return $this->setPropertyValue ??= new SetPropertyValue(
            $this->getColumnNameToPropertyName(),
            $this->getTypeCaster()
        );
    }

    private function getGetPropertyValue(): GetPropertyValue
    {
        return $this->getPropertyValue ??= new GetPropertyValue($this->getColumnNameToPropertyName());
    }

    private function getSetSnapshot(): SetSnapshot
    {
        return $this->setSnapshot ??= new SetSnapshot();
    }

    private function getGetSnapshot(): GetSnapshot
    {
        return $this->getSnapshot ??= new GetSnapshot();
    }

    private function getHydrateEntity(): HydrateEntity
    {
        return $this->hydrateEntity ??= new HydrateEntity(
            $this->getSetPropertyValue(),
            $this->getSetSnapshot()
        );
    }

    private function getDetectChanges(): DetectChanges
    {
        return $this->detectChanges ??= new DetectChanges(
            $this->getGetSnapshot(),
            $this->getGetPropertyValue()
        );
    }

    private function getCloneEntity(): CloneEntity
    {
        return $this->cloneEntity ??= new CloneEntity(
            $this->getGetSnapshot(),
            $this->getSetSnapshot()
        );
    }

    private function getDiffEntities(): DiffEntities
    {
        return $this->diffEntities ??= new DiffEntities();
    }

    private function getEntityToArray(): EntityToArray
    {
        return $this->entityToArray ??= new EntityToArray();
    }

    private function getLoadMultiple(): LoadMultiple
    {
        return $this->loadMultiple ??= new LoadMultiple(
            $this->getCloneEntity(),
            $this->getHydrateEntity()
        );
    }

    private function getUpdateProperties(): UpdateProperties
    {
        return $this->updateProperties ??= new UpdateProperties(
            $this->getSetPropertyValue()
        );
    }

    private function getHydrateAggregate(): HydrateAggregate
    {
        return $this->hydrateAggregate ??= new HydrateAggregate(
            $this->getColumnNameToPropertyName(),
            $this->getTypeCaster(),
            $this->getSetSnapshot()
        );
    }

    private function getGenerateUuid4(): GenerateUuid4
    {
        return $this->generateUuid4 ??= new GenerateUuid4();
    }

    private function getGenerateUuid7(): GenerateUuid7
    {
        return $this->generateUuid7 ??= new GenerateUuid7();
    }
}
