<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use DateTimeImmutable;
use JardisSupport\Data\Handler\ToSnapshotValue;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ToSnapshotValue handler.
 */
class ToSnapshotValueTest extends TestCase
{
    private ToSnapshotValue $toSnapshotValue;

    protected function setUp(): void
    {
        $this->toSnapshotValue = new ToSnapshotValue();
    }

    public function testConvertsDateTimeToString(): void
    {
        $dateTime = new DateTime('2024-01-15 14:30:45');

        $result = $this->toSnapshotValue->__invoke($dateTime);

        $this->assertSame('2024-01-15 14:30:45', $result);
    }

    public function testConvertsDateTimeImmutableToString(): void
    {
        $dateTime = new DateTimeImmutable('2024-06-20 09:15:00');

        $result = $this->toSnapshotValue->__invoke($dateTime);

        $this->assertSame('2024-06-20 09:15:00', $result);
    }

    public function testConvertsBackedEnumToValue(): void
    {
        $result = $this->toSnapshotValue->__invoke(GatewayType::Electricity);

        $this->assertSame('ELECTRICITY', $result);
    }

    public function testPassesThroughNull(): void
    {
        $result = $this->toSnapshotValue->__invoke(null);

        $this->assertNull($result);
    }

    public function testPassesThroughString(): void
    {
        $result = $this->toSnapshotValue->__invoke('hello');

        $this->assertSame('hello', $result);
    }

    public function testPassesThroughInt(): void
    {
        $result = $this->toSnapshotValue->__invoke(42);

        $this->assertSame(42, $result);
    }

    public function testPassesThroughFloat(): void
    {
        $result = $this->toSnapshotValue->__invoke(3.14);

        $this->assertSame(3.14, $result);
    }

    public function testPassesThroughBool(): void
    {
        $this->assertTrue($this->toSnapshotValue->__invoke(true));
        $this->assertFalse($this->toSnapshotValue->__invoke(false));
    }
}
