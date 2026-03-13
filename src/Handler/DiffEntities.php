<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Compares two entities of the same class and returns their differences.
 *
 * Compares all properties (ignoring __snapshot) and returns changed values
 * from the second entity.
 *
 * Value comparison for:
 * - Scalars (int, string, bool, float)
 * - DateTime/DateTimeImmutable (compares timestamps)
 *
 * Skips comparison for:
 * - Other objects (entities, value objects)
 * - Arrays
 */
class DiffEntities
{
    /** @var array<string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /**
     * Compare two entities and return differences.
     *
     * @param object $entity1 First entity (reference)
     * @param object $entity2 Second entity (compare against)
     * @return array<string, mixed> Map of property names to values from entity2 that differ from entity1
     */
    public function __invoke(object $entity1, object $entity2): array
    {
        $class1 = get_class($entity1);
        $class2 = get_class($entity2);

        if ($class1 !== $class2) {
            throw new InvalidArgumentException(
                "Cannot diff entities of different classes: $class1 vs $class2"
            );
        }

        if (!isset($this->reflectionCache[$class1])) {
            $this->reflectionCache[$class1] = new ReflectionClass($entity1);
        }

        $reflection = $this->reflectionCache[$class1];
        $differences = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            // Skip snapshot property
            if ($propertyName === '__snapshot') {
                continue;
            }

            $property->setAccessible(true);

            // Skip uninitialized properties
            if (!$property->isInitialized($entity1) && !$property->isInitialized($entity2)) {
                continue;
            }

            // Handle one initialized, one not
            if ($property->isInitialized($entity1) !== $property->isInitialized($entity2)) {
                if ($property->isInitialized($entity2)) {
                    $differences[$propertyName] = $property->getValue($entity2);
                }
                continue;
            }

            $value1 = $property->getValue($entity1);
            $value2 = $property->getValue($entity2);

            if (!$this->valuesAreEqual($value1, $value2)) {
                $differences[$propertyName] = $value2;
            }
        }

        return $differences;
    }

    /**
     * Compare two values for equality.
     *
     * Uses value comparison for:
     * - Scalars (int, string, bool, float)
     * - DateTime/DateTimeImmutable (timestamp-based)
     *
     * Skips comparison for:
     * - Other objects (always considered equal to avoid false positives)
     * - Arrays (always considered equal to avoid false positives)
     *
     * @param mixed $value1
     * @param mixed $value2
     * @return bool True if values are equal
     */
    private function valuesAreEqual(mixed $value1, mixed $value2): bool
    {
        // Strict equality for scalars
        if ($value1 === $value2) {
            return true;
        }

        // DateTime/DateTimeImmutable: Compare timestamps (value comparison, cross-type)
        if (
            ($value1 instanceof DateTime || $value1 instanceof DateTimeImmutable)
            && ($value2 instanceof DateTime || $value2 instanceof DateTimeImmutable)
        ) {
            return $value1->getTimestamp() === $value2->getTimestamp();
        }

        // Skip comparison for arrays (always equal)
        if (is_array($value1) && is_array($value2)) {
            return true;
        }

        // Skip comparison for other objects (always equal)
        if (is_object($value1) && is_object($value2)) {
            return true;
        }

        // Different types = different
        return false;
    }
}
