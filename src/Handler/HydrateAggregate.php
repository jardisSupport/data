<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Hydrates an aggregate with nested data structures.
 *
 * Handles recursive hydration of:
 * - Nested objects → instantiates and hydrates child objects
 * - Arrays of objects → instantiates and hydrates each element
 * - Scalar values → sets directly
 *
 * Performance: Caches reflection data per class to minimize overhead.
 */
class HydrateAggregate
{
    /**
     * Cache of ReflectionClass instances by class name.
     *
     * @var array<string, ReflectionClass<object>>
     */
    private array $reflectionCache = [];

    /**
     * Cache of properties per class (already setAccessible).
     *
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $propertiesCache = [];

    public function __construct(
        private readonly ColumnNameToPropertyName $columnNameToPropertyName,
        private readonly TypeCaster $typeCaster,
        private readonly SetSnapshot $setSnapshot,
        private readonly GetPropertyValue $getPropertyValue
    ) {
    }

    /**
     * Hydrate an aggregate from nested array data.
     *
     * @param object $aggregate The aggregate to hydrate
     * @param array<string, mixed> $data Nested array data
     * @return void
     */
    public function __invoke(object $aggregate, array $data): void
    {
        $className = get_class($aggregate);

        if (!isset($this->propertiesCache[$className])) {
            $this->cacheClassProperties($className);
        }

        $properties = $this->propertiesCache[$className];

        foreach ($data as $key => $value) {
            $propertyName = ($this->columnNameToPropertyName)($key);

            if (!isset($properties[$propertyName])) {
                continue;
            }

            $property = $properties[$propertyName];
            $hydratedValue = $this->hydrateValue($value, $property, $aggregate);

            // Try setter first
            $setterMethod = 'set' . ucfirst($propertyName);
            if (method_exists($aggregate, $setterMethod)) {
                $aggregate->$setterMethod($hydratedValue);
            } else {
                $property->setValue($aggregate, $hydratedValue);
            }
        }

        // Build snapshot from typed property values for scalars, raw data for arrays/objects
        $snapshot = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $snapshot[$key] = ($this->getPropertyValue)($aggregate, $key);
            } else {
                $snapshot[$key] = $value;
            }
        }

        // Set snapshot
        ($this->setSnapshot)($aggregate, $snapshot);
    }

    /**
     * Hydrate a value based on property type.
     *
     * @param mixed $value
     * @param ReflectionProperty $property
     * @param object $parentObject The parent object containing this property
     * @return mixed
     */
    private function hydrateValue(mixed $value, ReflectionProperty $property, object $parentObject): mixed
    {
        if ($value === null) {
            return null;
        }

        // Get property type
        $type = $property->getType();

        // Handle named types (objects with specific class type hint)
        // In real aggregates, all nested objects have concrete type hints like Counter, Gateway, Address
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // If value is already the correct object type, return it
            if (is_object($value) && $value instanceof $typeName) {
                return $value;
            }

            // If value is array and type is specific class, hydrate into object
            if (is_array($value) && class_exists($typeName)) {
                return $this->hydrateObject($value, $typeName);
            }
        }

        // Handle arrays (may contain nested objects with add*() methods)
        if (is_array($value)) {
            return $this->hydrateArray($value, $property);
        }

        // Use TypeCaster for scalar conversions (int, string, DateTime, etc.)
        return ($this->typeCaster)($value, $property);
    }

    /**
     * Hydrate an array (may contain objects).
     *
     * @param array<mixed> $array
     * @param ReflectionProperty $property
     * @return array<mixed>|object
     */
    private function hydrateArray(array $array, ReflectionProperty $property): array|object
    {
        $elementType = $this->getArrayElementType($property);

        // Check if array is associative (object data) or indexed (list of objects)
        if ($this->isAssociativeArray($array)) {
            if ($elementType !== null) {
                // Check if values are arrays → map of objects (e.g. array<string, Address>)
                $firstValue = reset($array);
                if (is_array($firstValue)) {
                    $result = [];
                    foreach ($array as $key => $item) {
                        $result[$key] = is_array($item)
                            ? $this->hydrateObject($item, $elementType)
                            : $item;
                    }
                    return $result;
                }

                // Values are scalars → single object data
                return $this->hydrateObject($array, $elementType);
            }

            // If no type found, return as-is
            return $array;
        }

        // Indexed array - hydrate each element
        $result = [];

        foreach ($array as $key => $item) {
            if (is_array($item) && $elementType !== null) {
                $result[$key] = $this->hydrateObject($item, $elementType);
            } else {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Hydrate an object from array data.
     *
     * @param array<string, mixed> $data
     * @param class-string $className
     * @return object
     * @throws \ReflectionException
     */
    private function hydrateObject(array $data, string $className): object
    {
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new ReflectionClass($className);
        }

        $reflection = $this->reflectionCache[$className];

        // Create instance without constructor
        $object = $reflection->newInstanceWithoutConstructor();

        // Cache properties for this class
        if (!isset($this->propertiesCache[$className])) {
            $this->cacheClassProperties($className);
        }

        $properties = $this->propertiesCache[$className];

        // Hydrate nested properties
        foreach ($data as $key => $value) {
            $propertyName = ($this->columnNameToPropertyName)($key);

            if (!isset($properties[$propertyName])) {
                continue;
            }

            $property = $properties[$propertyName];
            $hydratedValue = $this->hydrateValue($value, $property, $object);

            // Try setter first
            $setterMethod = 'set' . ucfirst($propertyName);
            if (method_exists($object, $setterMethod)) {
                $object->$setterMethod($hydratedValue);
            } else {
                $property->setValue($object, $hydratedValue);
            }
        }

        // Build snapshot from typed property values for scalars, raw data for arrays/objects
        $snapshot = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $snapshot[$key] = ($this->getPropertyValue)($object, $key);
            } else {
                $snapshot[$key] = $value;
            }
        }

        // Set snapshot
        ($this->setSnapshot)($object, $snapshot);

        return $object;
    }

    /**
     * Check if array is associative.
     *
     * @param array<mixed> $array
     * @return bool
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Get array element type from property docblock for arrays of objects.
     *
     * @param ReflectionProperty $property
     * @return class-string<object>|null
     */
    private function getArrayElementType(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();

        if ($docComment === false) {
            return null;
        }

        // Match @var ClassName[], @var array<int, ClassName[]>, or @var array<int, ClassName>
        $pattern = '/@var\s+(?:array<[^,]+,\s*)?([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)(?:\[\]|>)/';
        if (preg_match($pattern, $docComment, $matches)) {
            $className = $matches[1];

            // Resolve relative class names
            if ($className[0] !== '\\') {
                $declaringClass = $property->getDeclaringClass();
                $namespace = $declaringClass->getNamespaceName();

                if ($namespace) {
                    $className = $namespace . '\\' . $className;
                }
            }

            $resolvedClass = ltrim($className, '\\');

            // PHPStan requirement: ensure it's a valid class
            /** @var class-string<object>|null */
            return class_exists($resolvedClass) ? $resolvedClass : null;
        }

        return null;
    }

    /**
     * Cache all properties for a class (excluding __snapshot).
     *
     * @param class-string $className
     * @return void
     * @throws \ReflectionException
     */
    private function cacheClassProperties(string $className): void
    {
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = new ReflectionClass($className);
        }

        $reflection = $this->reflectionCache[$className];
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            // Skip snapshot property
            if ($propertyName === '__snapshot') {
                continue;
            }

            $property->setAccessible(true);
            $properties[$propertyName] = $property;
        }

        $this->propertiesCache[$className] = $properties;
    }
}
