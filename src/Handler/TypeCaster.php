<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use DateTime;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Type-casts values based on property type hints.
 *
 * Handles conversion from database values to PHP types:
 * - String → DateTime (supports Y-m-d H:i:s, Y-m-d, H:i:s formats)
 * - String → int/bool
 * - String → float
 *
 * Performance: Caches type names per ReflectionProperty object hash.
 */
class TypeCaster
{
    /**
     * Cache of type names by property object hash.
     *
     * @var array<string, string|null>
     */
    private array $typeCache = [];

    /**
     * Cast value to match property type.
     *
     * @param mixed $value Value from database
     * @param ReflectionProperty $property Target property
     * @return mixed Type-casted value
     */
    public function __invoke(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return $value;
        }

        $cacheKey = spl_object_hash($property);

        if (!isset($this->typeCache[$cacheKey])) {
            $type = $property->getType();
            $this->typeCache[$cacheKey] = ($type instanceof ReflectionNamedType)
                ? strtolower($type->getName())
                : null;
        }

        $typeName = $this->typeCache[$cacheKey];

        if ($typeName === null) {
            return $value;
        }

        return match ($typeName) {
            'datetime' => $this->parseDateTime($value),
            'int', 'integer' => (int) $value,
            'bool', 'boolean' => (bool) ((int) $value),
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Parse DateTime from string.
     *
     * Tries multiple formats: Y-m-d H:i:s, Y-m-d, H:i:s
     *
     * @param mixed $value
     * @return DateTime|null
     */
    private function parseDateTime(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if (!is_string($value) || empty($value)) {
            return null;
        }

        // Try datetime format
        $result = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($result !== false) {
            return $result;
        }

        // Try date format
        $result = DateTime::createFromFormat('Y-m-d', $value);
        if ($result !== false) {
            return $result;
        }

        // Try time format
        $result = DateTime::createFromFormat('H:i:s', $value);
        if ($result !== false) {
            return $result;
        }

        return null;
    }
}
