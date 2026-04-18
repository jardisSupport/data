<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Hydrates an entity with data from database.
 *
 * Uses value-based detection for scalars/DateTime/BackedEnum. For arrays,
 * checks the property type: only sets if the property is typed as 'array'.
 * Snapshot stores scalar representations only (DateTime → string, BackedEnum → value).
 */
class HydrateEntity
{
    public function __construct(
        private readonly SetPropertyValue $setPropertyValue,
        private readonly SetSnapshot $setSnapshot,
        private readonly GetSnapshot $getSnapshot,
        private readonly GetPropertyValue $getPropertyValue,
        private readonly ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly ToSnapshotValue $toSnapshotValue = new ToSnapshotValue()
    ) {
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @param bool $updateSnapshot Whether to update the snapshot (false for apply)
     * @return void
     */
    public function __invoke(object $entity, array $data, bool $updateSnapshot = true): void
    {
        $reflection = new ReflectionClass($entity);

        $snapshot = $updateSnapshot ? ($this->getSnapshot)($entity) : null;
        foreach ($data as $columnName => $value) {
            if (!$this->shouldHydrate($value, $columnName, $reflection)) {
                continue;
            }

            ($this->setPropertyValue)($entity, $columnName, $value);

            if ($snapshot !== null) {
                $typedValue = ($this->getPropertyValue)($entity, $columnName);
                $snapshot[$columnName] = ($this->toSnapshotValue)($typedValue);
            }
        }

        if ($snapshot !== null) {
            ($this->setSnapshot)($entity, $snapshot);
        }
    }

    /**
     * Check if a value should be hydrated into the entity.
     *
     * @param mixed $value
     * @param string $columnName
     * @param ReflectionClass<object> $reflection
     * @return bool
     */
    private function shouldHydrate(mixed $value, string $columnName, ReflectionClass $reflection): bool
    {
        if (
            $value === null || is_scalar($value)
            || $value instanceof DateTimeInterface
            || $value instanceof BackedEnum
        ) {
            return true;
        }

        if (is_array($value)) {
            return $this->propertyAcceptsArray($columnName, $reflection)
                && !$this->looksLikeRelationData($value);
        }

        return false;
    }

    /**
     * Check if a property is typed as 'array' (accepts raw array values).
     *
     * @param string $columnName
     * @param ReflectionClass<object> $reflection
     * @return bool
     */
    private function propertyAcceptsArray(string $columnName, ReflectionClass $reflection): bool
    {
        $propertyName = ($this->columnNameToPropertyName)($columnName);

        if (!$reflection->hasProperty($propertyName)) {
            return false;
        }

        $type = $reflection->getProperty($propertyName)->getType();

        if ($type === null) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() === 'array') {
                    return true;
                }
            }
            return false;
        }

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return $type->getName() === 'array' || $type->getName() === 'mixed';
    }

    /**
     * Check if an array looks like MANY-relation data (indexed array of associative arrays).
     *
     * Pattern: [['id' => 10, 'name' => 'foo'], ['id' => 20, 'name' => 'bar']]
     * This is the shape of nested entity data from aggregate hydration.
     *
     * NOT relation data:
     * - Scalar arrays: ['php', 'testing']
     * - Assoc config: ['db' => ['host' => 'x']]
     * - Matrices: [[1,2],[3,4]]
     * - Empty arrays: []
     *
     * @param array<mixed> $array
     * @return bool
     */
    private function looksLikeRelationData(array $array): bool
    {
        if (empty($array) || !array_is_list($array)) {
            return false;
        }

        $firstValue = reset($array);

        // Indexed list whose first element is an associative array → MANY-relation data
        // [[1,2],[3,4]] is a matrix (list of lists), not relation data
        return is_array($firstValue) && !array_is_list($firstValue);
    }
}
