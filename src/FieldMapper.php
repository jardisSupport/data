<?php

declare(strict_types=1);

namespace JardisSupport\Data;

use JardisSupport\Contract\Data\FieldMapperInterface;

/**
 * Bidirectional array-key mapping between domain field names and database column names.
 *
 * Renames array keys using an explicit map. Keys not in the map pass through unchanged.
 * Map format: [domainName => columnName, ...] — identical to the Builder's FieldMap.
 */
class FieldMapper implements FieldMapperInterface
{
    /**
     * Renames array keys from domain field names to database column names.
     *
     * @param array<string, mixed> $data Input with domain field names as keys
     * @param array<string, string> $map Domain-to-column mapping
     * @return array<string, mixed> Output with column names as keys
     */
    public function toColumns(array $data, array $map): array
    {
        if ($map === []) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }

        return $result;
    }

    /**
     * Renames array keys from database column names to domain field names.
     *
     * Applies recursively to nested arrays (for aggregate query responses).
     *
     * @param array<string, mixed> $data Input with column names as keys
     * @param array<string, string> $map Domain-to-column mapping (same direction as toColumns)
     * @return array<string, mixed> Output with domain field names as keys
     */
    public function fromColumns(array $data, array $map): array
    {
        if ($map === []) {
            return $data;
        }

        $reverseMap = array_flip($map);
        return $this->applyMap($data, $reverseMap);
    }

    /**
     * Maps a hierarchical aggregate array using per-entity mappings from a provider.
     *
     * @param array<string, mixed> $data Hierarchical array (e.g. from aggregateToArray)
     * @param callable(string): array<string, string> $mapProvider Returns [domainName => columnName] per entity
     * @param string $entityName Name of the current entity level
     * @return array<string, mixed> Mapped array with only mapped field names
     */
    public function fromAggregate(array $data, callable $mapProvider, string $entityName): array
    {
        $reverseMap = array_flip($mapProvider($entityName));
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = array_is_list($value)
                    ? array_map(fn(array $item) => $this->fromAggregate($item, $mapProvider, $key), $value)
                    : $this->fromAggregate($value, $mapProvider, $key);
                $result[$key] = $child;
                continue;
            }

            if (isset($reverseMap[$key])) {
                $result[$reverseMap[$key]] = $value;
            }
        }

        return $result;
    }

    /**
     * Applies a key-renaming map recursively.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    private function applyMap(array $data, array $map): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $mappedKey = $map[$key] ?? $key;
            $result[$mappedKey] = is_array($value) ? $this->applyMap($value, $map) : $value;
        }

        return $result;
    }
}
