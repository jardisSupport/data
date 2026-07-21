<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for ColumnNameToPropertyName
 */
class ColumnNameToPropertyNameTest extends TestCase
{
    private ColumnNameToPropertyName $handler;

    protected function setUp(): void
    {
        $this->handler = new ColumnNameToPropertyName();
    }

    public function testConvertsSnakeCaseToCamelCase(): void
    {
        $result = $this->handler->__invoke('first_name');

        $this->assertSame('firstName', $result);
    }

    public function testHandlesSingleWord(): void
    {
        $result = $this->handler->__invoke('name');

        $this->assertSame('name', $result);
    }

    public function testHandlesMultipleUnderscores(): void
    {
        $result = $this->handler->__invoke('user_first_last_name');

        $this->assertSame('userFirstLastName', $result);
    }

    public function testHandlesAlreadyCamelCase(): void
    {
        $result = $this->handler->__invoke('firstName');

        $this->assertSame('firstName', $result);
    }
}
