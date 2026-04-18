<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Type-casts values based on property type hints.
 *
 * Handles conversion from database values to PHP types:
 * - String → DateTime (supports Y-m-d H:i:s, Y-m-d, H:i:s formats)
 * - String → DateTimeImmutable
 * - String → int/bool/float
 * - String → BackedEnum (via ::from())
 *
 * Stateless — no internal caches.
 */
class TypeCaster
{
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

        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // Handle BackedEnum before strtolower — enum names are case-sensitive
        if (is_a($typeName, BackedEnum::class, true)) {
            if ($value instanceof $typeName) {
                return $value;
            }
            return $typeName::from($value);
        }

        return match (strtolower($typeName)) {
            'datetime' => $this->parseDateTime($value),
            'datetimeimmutable' => $this->parseDateTimeImmutable($value),
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
     * @param mixed $value
     * @return DateTime|null
     */
    private function parseDateTime(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if ($value instanceof DateTimeImmutable) {
            return DateTime::createFromImmutable($value);
        }

        if (!is_string($value) || empty($value)) {
            return null;
        }

        $result = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($result !== false) {
            return $result;
        }

        $result = DateTime::createFromFormat('Y-m-d|', $value);
        if ($result !== false) {
            return $result;
        }

        $result = DateTime::createFromFormat('H:i:s|', $value);
        if ($result !== false) {
            return $result;
        }

        return null;
    }

    /**
     * Parse DateTimeImmutable from string.
     *
     * @param mixed $value
     * @return DateTimeImmutable|null
     */
    private function parseDateTimeImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        if (!is_string($value) || empty($value)) {
            return null;
        }

        $result = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if ($result !== false) {
            return $result;
        }

        $result = DateTimeImmutable::createFromFormat('Y-m-d|', $value);
        if ($result !== false) {
            return $result;
        }

        $result = DateTimeImmutable::createFromFormat('H:i:s|', $value);
        if ($result !== false) {
            return $result;
        }

        return null;
    }
}
