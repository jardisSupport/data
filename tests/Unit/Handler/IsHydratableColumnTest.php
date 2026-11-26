<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTimeImmutable;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\IsHydratableColumn;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for IsHydratableColumn handler.
 */
class IsHydratableColumnTest extends TestCase
{
    private IsHydratableColumn $isHydratableColumn;

    protected function setUp(): void
    {
        $this->isHydratableColumn = new IsHydratableColumn(new ColumnNameToPropertyName());
    }

    public function testAcceptsNullScalarDateTimeAndEnum(): void
    {
        $reflection = new ReflectionClass(Counter::class);

        $this->assertTrue(($this->isHydratableColumn)(null, 'counter_number', $reflection));
        $this->assertTrue(($this->isHydratableColumn)('N1', 'counter_number', $reflection));
        $this->assertTrue(($this->isHydratableColumn)(new DateTimeImmutable(), 'active_from', $reflection));
        $this->assertTrue(($this->isHydratableColumn)(GatewayType::Gas, 'counter_number', $reflection));
    }

    public function testRejectsObjects(): void
    {
        $this->assertFalse(
            ($this->isHydratableColumn)(new \stdClass(), 'counter_gateway', new ReflectionClass(Counter::class))
        );
    }

    public function testArrayOnlyForArrayPropertyWithoutRelationShape(): void
    {
        $reflection = new ReflectionClass(Counter::class);

        $this->assertTrue(($this->isHydratableColumn)(['a', 'b'], 'counter_register', $reflection));
        $this->assertTrue(($this->isHydratableColumn)([], 'counter_register', $reflection));
        $this->assertFalse(($this->isHydratableColumn)([['id' => 1]], 'counter_register', $reflection));
        $this->assertFalse(($this->isHydratableColumn)(['id' => 3], 'counter_gateway', $reflection));
        $this->assertFalse(($this->isHydratableColumn)(['a'], 'unknown_column', $reflection));
    }

    public function testArrayForUntypedMixedAndUnionArrayProperty(): void
    {
        $entity = new class {
            public $untyped;
            public mixed $anything;
            public array|string|null $union = null;
            public ?string $text = null;
        };
        $reflection = new ReflectionClass($entity);

        $this->assertTrue(($this->isHydratableColumn)(['a'], 'untyped', $reflection));
        $this->assertTrue(($this->isHydratableColumn)(['a'], 'anything', $reflection));
        $this->assertTrue(($this->isHydratableColumn)(['a'], 'union', $reflection));
        $this->assertFalse(($this->isHydratableColumn)(['a'], 'text', $reflection));
    }
}
