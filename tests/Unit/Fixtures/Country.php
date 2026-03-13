<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

/**
 * Fixture: Simple value object for testing deeply nested object hydration
 */
class Country
{
    private string $code;
    private string $name;

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
