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
    /**
     * @param string $columnName
     * @return string
     */
    public function __invoke(string $columnName): string
    {
        // Convert snake_case to camelCase
        return lcfirst(str_replace('_', '', ucwords($columnName, '_')));
    }
}
