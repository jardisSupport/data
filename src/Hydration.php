<?php

declare(strict_types=1);

namespace JardisSupport\Data;

use JardisSupport\Data\Handler\AggregateToArray;
use JardisSupport\Data\Handler\CloneAggregate;
use JardisSupport\Data\Handler\CloneEntity;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\DetectChanges;
use JardisSupport\Data\Handler\DiffEntities;
use JardisSupport\Data\Handler\EntityToArray;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\HydrateEntity;
use JardisSupport\Data\Handler\LoadMultiple;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\ToSnapshotValue;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Contract\Data\HydrationInterface;

/**
 * Entity hydration, change tracking and snapshot management.
 *
 * Provides hydration from database rows, change detection via snapshots,
 * entity cloning, diffing and array conversion.
 * Uses value-based detection (scalar/null/DateTime/BackedEnum = DB column).
 */
class Hydration implements HydrationInterface
{
    private ?HydrateEntity $hydrateEntity = null;
    private ?HydrateAggregate $hydrateAggregate = null;
    private ?DetectChanges $detectChanges = null;
    private ?GetSnapshot $getSnapshot = null;
    private ?CloneEntity $cloneEntity = null;
    private ?CloneAggregate $cloneAggregate = null;
    private ?DiffEntities $diffEntities = null;
    private ?EntityToArray $entityToArray = null;
    private ?AggregateToArray $aggregateToArray = null;
    private ?LoadMultiple $loadMultiple = null;

    // Cached shared helper instances
    private ?ColumnNameToPropertyName $columnNameToPropertyName = null;
    private ?SetPropertyValue $setPropertyValue = null;
    private ?GetPropertyValue $getPropertyValue = null;
    private ?SetSnapshot $setSnapshot = null;
    private ?TypeCaster $typeCaster = null;
    private ?ToSnapshotValue $toSnapshotValue = null;
    /**
     * Hydrates an entity with data from a database.
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
     * Applies data to an entity without updating the snapshot.
     *
     * @template T of object
     * @param T $entity
     * @param array<string, mixed> $data
     * @return T
     */
    public function apply(object $entity, array $data): object
    {
        ($this->getHydrateEntity())($entity, $data, false);
        return $entity;
    }

    /**
     * Hydrates an aggregate with nested data structures.
     *
     * @template T of object
     * @param T $aggregate
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrateAggregate(object $aggregate, array $data): object
    {
        ($this->getHydrateAggregate())($aggregate, $data);
        return $aggregate;
    }

    /**
     * Detects changes between current entity state and snapshot.
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
     * Creates a flat clone of an entity (DB-column properties only).
     *
     * @param object $entity The entity to clone
     * @return object The cloned entity
     * @throws \ReflectionException
     */
    public function clone(object $entity): object
    {
        return ($this->getCloneEntity())($entity);
    }

    /**
     * Creates a deep clone of an aggregate (full object graph).
     *
     * @param object $entity The aggregate to clone
     * @return object The cloned aggregate
     * @throws \ReflectionException
     */
    public function cloneAggregate(object $entity): object
    {
        return ($this->getCloneAggregate())($entity);
    }

    /**
     * Compares two entities of the same class and returns differences.
     *
     * @param object $entity1 First entity (reference)
     * @param object $entity2 Second entity (compare against)
     * @return array<string, mixed> Map of column names to values from entity2 that differ
     */
    public function diff(object $entity1, object $entity2): array
    {
        return ($this->getDiffEntities())($entity1, $entity2);
    }

    /**
     * Converts an entity to a flat associative array (entity-level only).
     *
     * @param object $entity The entity to convert
     * @return array<string, mixed> Associative array with column names as keys
     */
    public function toArray(object $entity): array
    {
        return ($this->getEntityToArray())($entity);
    }

    /**
     * Converts an aggregate to a nested associative array (full object graph).
     *
     * @param object $entity The aggregate to convert
     * @return array<string, mixed> Nested associative array of the full object graph
     */
    public function aggregateToArray(object $entity): array
    {
        return ($this->getAggregateToArray())($entity);
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

    private function getToSnapshotValue(): ToSnapshotValue
    {
        return $this->toSnapshotValue ??= new ToSnapshotValue();
    }

    private function getHydrateEntity(): HydrateEntity
    {
        return $this->hydrateEntity ??= new HydrateEntity(
            $this->getSetPropertyValue(),
            $this->getSetSnapshot(),
            $this->getGetSnapshot(),
            $this->getGetPropertyValue(),
            $this->getColumnNameToPropertyName(),
            $this->getToSnapshotValue()
        );
    }

    private function getDetectChanges(): DetectChanges
    {
        return $this->detectChanges ??= new DetectChanges(
            $this->getGetSnapshot(),
            $this->getGetPropertyValue(),
            $this->getToSnapshotValue()
        );
    }

    private function getCloneEntity(): CloneEntity
    {
        return $this->cloneEntity ??= new CloneEntity(
            $this->getGetSnapshot(),
            $this->getSetSnapshot(),
            $this->getColumnNameToPropertyName()
        );
    }

    private function getCloneAggregate(): CloneAggregate
    {
        return $this->cloneAggregate ??= new CloneAggregate(
            $this->getGetSnapshot(),
            $this->getSetSnapshot()
        );
    }

    private function getDiffEntities(): DiffEntities
    {
        return $this->diffEntities ??= new DiffEntities(
            $this->getGetSnapshot(),
            $this->getColumnNameToPropertyName()
        );
    }

    private function getEntityToArray(): EntityToArray
    {
        return $this->entityToArray ??= new EntityToArray(
            $this->getGetSnapshot(),
            $this->getColumnNameToPropertyName()
        );
    }

    private function getAggregateToArray(): AggregateToArray
    {
        return $this->aggregateToArray ??= new AggregateToArray(
            $this->getGetSnapshot(),
            $this->getColumnNameToPropertyName()
        );
    }

    private function getLoadMultiple(): LoadMultiple
    {
        return $this->loadMultiple ??= new LoadMultiple(
            $this->getHydrateEntity()
        );
    }

    private function getHydrateAggregate(): HydrateAggregate
    {
        return $this->hydrateAggregate ??= new HydrateAggregate(
            $this->getColumnNameToPropertyName(),
            $this->getTypeCaster(),
            $this->getSetSnapshot(),
            $this->getGetSnapshot(),
            $this->getGetPropertyValue(),
            $this->getToSnapshotValue()
        );
    }
}
