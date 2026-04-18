<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Converts PHP property name to database column name.
 *
 * Examples:
 * - userId → user_id
 * - createdAt → created_at
 * - status → status
 */
class PropertyNameToColumnName
{
    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @param string $propertyName
     * @return string
     */
    public function __invoke(string $propertyName): string
    {
        return $this->cache[$propertyName] ??= strtolower(
            (string) preg_replace('/[A-Z]/', '_$0', $propertyName)
        );
    }
}
