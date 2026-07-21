<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Converts database column name to property name.
 *
 * Examples:
 * - user_id → userId
 * - created_at → createdAt
 * - status → status
 */
class ColumnNameToPropertyName
{
    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @param string $columnName
     * @return string
     */
    public function __invoke(string $columnName): string
    {
        return $this->cache[$columnName] ??= lcfirst(str_replace('_', '', ucwords($columnName, '_')));
    }
}
