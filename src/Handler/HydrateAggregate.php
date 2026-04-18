<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Hydrates an aggregate with nested data structures.
 *
 * Uses value-based detection instead of #[Relation] attributes:
 * - scalar/null/DateTime/BackedEnum → DB column (set directly)
 * - associative array + property type is class → ONE relation
 * - indexed array → MANY relation
 *
 * Type resolution for MANY relations: adder parameter type, then @var docblock fallback.
 */
class HydrateAggregate
{
    public function __construct(
        private readonly ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly TypeCaster $typeCaster,
        private readonly SetSnapshot $setSnapshot,
        private readonly GetSnapshot $getSnapshot,
        private readonly GetPropertyValue $getPropertyValue,
        private readonly ToSnapshotValue $toSnapshotValue = new ToSnapshotValue()
    ) {
    }

    /**
     * Hydrate an aggregate from nested array data.
     *
     * @param object $aggregate The aggregate to hydrate
     * @param array<string|int, mixed> $data Nested array data (may contain numeric collection indices)
     * @return void
     */
    public function __invoke(object $aggregate, array $data): void
    {
        $reflection = new ReflectionClass($aggregate);
        $properties = $this->getProperties($reflection);

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $propertyName = ($this->columnNameToPropertyName)($key);

            if (!isset($properties[$propertyName])) {
                continue;
            }

            $property = $properties[$propertyName];
            $hydratedValue = $this->hydrateValue($value, $property, $aggregate);

            $setterMethod = 'set' . ucfirst($propertyName);
            if (method_exists($aggregate, $setterMethod)) {
                $aggregate->$setterMethod($hydratedValue);
            } else {
                $property->setValue($aggregate, $hydratedValue);
            }
        }

        // Build snapshot — only DB-column values
        $snapshot = $this->buildSnapshot($data, $aggregate);
        ($this->setSnapshot)($aggregate, $snapshot);
    }

    /**
     * Hydrate a value based on property type.
     *
     * @param mixed $value
     * @param ReflectionProperty $property
     * @param object $parentObject
     * @return mixed
     */
    private function hydrateValue(mixed $value, ReflectionProperty $property, object $parentObject): mixed
    {
        if ($value === null) {
            return null;
        }

        // DB-column scalar values → type-cast
        if ($this->isDbColumnValue($value)) {
            return ($this->typeCaster)($value, $property);
        }

        // Get property type
        $type = $property->getType();

        // Named type → ONE relation (property type is a class)
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if (is_object($value) && $value instanceof $typeName) {
                return $value;
            }

            if (is_array($value) && class_exists($typeName)) {
                return $this->hydrateObject($value, $typeName);
            }
        }

        // Array property → could be MANY relation or scalar array
        if (is_array($value)) {
            return $this->hydrateArray($value, $property, $parentObject);
        }

        return $value;
    }

    /**
     * Hydrate an array property.
     *
     * @param array<mixed> $array
     * @param ReflectionProperty $property
     * @param object $parentObject
     * @return array<mixed>
     */
    private function hydrateArray(array $array, ReflectionProperty $property, object $parentObject): array
    {
        $elementType = $this->resolveArrayElementType($property, $parentObject);

        if (!array_is_list($array)) {
            if ($elementType !== null) {
                $firstValue = reset($array);
                if (is_array($firstValue)) {
                    // Map of objects
                    $result = [];
                    foreach ($array as $key => $item) {
                        $result[$key] = is_array($item)
                            ? $this->hydrateObject($item, $elementType)
                            : $item;
                    }
                    return $result;
                }

                // Associative array with scalar values → single object data
                return [$this->hydrateObject($array, $elementType)];
            }

            return $array;
        }

        // Indexed array → MANY relation
        if ($elementType !== null) {
            $result = [];
            foreach ($array as $key => $item) {
                $result[$key] = is_array($item)
                    ? $this->hydrateObject($item, $elementType)
                    : $item;
            }
            return $result;
        }

        return $array;
    }

    /**
     * Hydrate an object from array data.
     *
     * @param array<string|int, mixed> $data
     * @param class-string $className
     * @return object
     */
    private function hydrateObject(array $data, string $className): object
    {
        $reflection = new ReflectionClass($className);
        $object = $reflection->newInstanceWithoutConstructor();
        $properties = $this->getProperties($reflection);

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $propertyName = ($this->columnNameToPropertyName)($key);

            if (!isset($properties[$propertyName])) {
                continue;
            }

            $property = $properties[$propertyName];
            $hydratedValue = $this->hydrateValue($value, $property, $object);

            $setterMethod = 'set' . ucfirst($propertyName);
            if (method_exists($object, $setterMethod)) {
                $object->$setterMethod($hydratedValue);
            } else {
                $property->setValue($object, $hydratedValue);
            }
        }

        // Build snapshot — only DB-column values
        $snapshot = $this->buildSnapshot($data, $object);
        ($this->setSnapshot)($object, $snapshot);

        return $object;
    }

    /**
     * Build snapshot from data — only DB-column values.
     *
     * @param array<string|int, mixed> $data
     * @param object $entity
     * @return array<string, mixed>
     */
    private function buildSnapshot(array $data, object $entity): array
    {
        $snapshot = ($this->getSnapshot)($entity);

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            if ($this->isDbColumnValue($value)) {
                $typedValue = ($this->getPropertyValue)($entity, $key);
                $snapshot[$key] = ($this->toSnapshotValue)($typedValue);
            }
        }

        return $snapshot;
    }

    /**
     * Resolve array element type from adder method parameter or docblock.
     *
     * @param ReflectionProperty $property
     * @param object $parentObject
     * @return class-string<object>|null
     */
    private function resolveArrayElementType(ReflectionProperty $property, object $parentObject): ?string
    {
        // Try adder method: addCounterRegister(CounterRegister $cr) → CounterRegister
        $propertyName = $property->getName();
        $singularName = str_ends_with($propertyName, 's')
            ? substr($propertyName, 0, -1)
            : $propertyName;
        $adderMethod = 'add' . ucfirst($singularName);

        if (method_exists($parentObject, $adderMethod)) {
            $adder = new ReflectionMethod($parentObject, $adderMethod);
            $params = $adder->getParameters();
            if (!empty($params)) {
                $paramType = $params[0]->getType();
                if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                    /** @var class-string<object> */
                    return $paramType->getName();
                }
            }
        }

        // Fallback: @var docblock
        return $this->getDocblockElementType($property);
    }

    /**
     * Get array element type from property docblock.
     *
     * @param ReflectionProperty $property
     * @return class-string<object>|null
     */
    private function getDocblockElementType(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $pattern = '/@var\s+(?:array<[^,]+,\s*)?([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)(?:\[\]|>)/';
        if (preg_match($pattern, $docComment, $matches)) {
            $className = $matches[1];

            if ($className[0] !== '\\') {
                $declaringClass = $property->getDeclaringClass();
                $namespace = $declaringClass->getNamespaceName();

                if ($namespace) {
                    $className = $namespace . '\\' . $className;
                }
            }

            $resolvedClass = ltrim($className, '\\');

            /** @var class-string<object>|null */
            return class_exists($resolvedClass) ? $resolvedClass : null;
        }

        return null;
    }

    /**
     * Check if a value represents a DB column value.
     *
     * @param mixed $value
     * @return bool
     */
    private function isDbColumnValue(mixed $value): bool
    {
        return $value === null
            || is_scalar($value)
            || $value instanceof DateTimeInterface
            || $value instanceof BackedEnum;
    }

    /**
     * Get properties for a reflection class (excluding __snapshot).
     *
     * @param ReflectionClass<object> $reflection
     * @return array<string, ReflectionProperty>
     */
    private function getProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            if ($propertyName === '__snapshot') {
                continue;
            }

            $properties[$propertyName] = $property;
        }

        return $properties;
    }
}
