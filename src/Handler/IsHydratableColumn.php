<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Decides whether a value in a row is a DB column value for an entity.
 *
 * Value-based detection: null, scalar, DateTimeInterface and BackedEnum are column values.
 * An array is a column value only if the property is typed as 'array' (or untyped/mixed)
 * and the array does not look like MANY-relation data. Everything else (objects,
 * relation data) is not a column value.
 */
class IsHydratableColumn
{
    public function __construct(
        private readonly ColumnNameToPropertyName $columnNameToPropertyName
    ) {
    }

    /**
     * @param mixed $value
     * @param string $columnName
     * @param ReflectionClass<object> $reflection
     * @return bool
     */
    public function __invoke(mixed $value, string $columnName, ReflectionClass $reflection): bool
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
