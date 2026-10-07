<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use JardisSupport\Data\Handler\ProjectWire;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProjectWire handler.
 */
class ProjectWireTest extends TestCase
{
    private ProjectWire $projectWire;
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->projectWire = new ProjectWire();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    // Zusage 1
    public function testUnmappedKeysPassThroughAndOrderIsKept(): void
    {
        $result = ($this->projectWire)(
            ['a' => 1, 'at' => '2024-01-15 10:00:00', 'b' => 'x'],
            ['at' => 'date-time', 'missing' => 'date']
        );

        $this->assertSame(['a', 'at', 'b'], array_keys($result));
        $this->assertSame(1, $result['a']);
        $this->assertSame('x', $result['b']);
        $this->assertArrayNotHasKey('missing', $result);
    }

    // Zusage 2
    public function testNullStaysNullForEveryFormat(): void
    {
        $result = ($this->projectWire)(
            ['a' => null, 'b' => null, 'c' => null],
            ['a' => 'date', 'b' => 'date-time', 'c' => 'time']
        );

        $this->assertSame(['a' => null, 'b' => null, 'c' => null], $result);
    }

    // Zusage 3
    public function testDateTimeObjectWallClockIsReadAsUtc(): void
    {
        $dt = new DateTime('2024-01-15 14:30:45', new DateTimeZone('Europe/Berlin'));

        $result = ($this->projectWire)(['at' => $dt], ['at' => 'date-time']);

        $this->assertSame('2024-01-15T14:30:45+00:00', $result['at']);
    }

    public function testDateTimeImmutableWallClockIsReadAsUtc(): void
    {
        $dt = new DateTimeImmutable('2024-07-01 09:00:00', new DateTimeZone('America/New_York'));

        $result = ($this->projectWire)(['at' => $dt], ['at' => 'date-time']);

        $this->assertSame('2024-07-01T09:00:00+00:00', $result['at']);
    }

    // Zusage 4
    public function testDateTimeStringWithoutOffsetIsReadAsUtc(): void
    {
        $result = ($this->projectWire)(
            ['a' => '2024-01-15 14:30:45', 'b' => '2024-01-15T14:30:45', 'c' => '2024-01-15 14:30:45.123456'],
            ['a' => 'date-time', 'b' => 'date-time', 'c' => 'date-time']
        );

        $this->assertSame('2024-01-15T14:30:45+00:00', $result['a']);
        $this->assertSame('2024-01-15T14:30:45+00:00', $result['b']);
        $this->assertSame('2024-01-15T14:30:45+00:00', $result['c']);
    }

    public function testDateTimeStringWithOffsetIsConvertedToUtc(): void
    {
        $result = ($this->projectWire)(
            ['a' => '2024-01-15 14:30:45+02:00', 'b' => '2024-01-15T14:30:45Z'],
            ['a' => 'date-time', 'b' => 'date-time']
        );

        $this->assertSame('2024-01-15T12:30:45+00:00', $result['a']);
        $this->assertSame('2024-01-15T14:30:45+00:00', $result['b']);
    }

    // Zusage 5
    public function testDateFromObjectStringAndDateTimeString(): void
    {
        $result = ($this->projectWire)(
            [
                'a' => new DateTime('2024-01-15 23:59:59', new DateTimeZone('Asia/Tokyo')),
                'b' => '2024-01-15',
                'c' => '2024-01-15 23:59:59',
            ],
            ['a' => 'date', 'b' => 'date', 'c' => 'date']
        );

        $this->assertSame('2024-01-15', $result['a']);
        $this->assertSame('2024-01-15', $result['b']);
        $this->assertSame('2024-01-15', $result['c']);
    }

    // Zusage 6
    public function testTimeFromObjectAndString(): void
    {
        $result = ($this->projectWire)(
            ['a' => new DateTimeImmutable('2024-01-15 08:05:09'), 'b' => '08:05:09', 'c' => '08:05:09.987'],
            ['a' => 'time', 'b' => 'time', 'c' => 'time']
        );

        $this->assertSame('08:05:09', $result['a']);
        $this->assertSame('08:05:09', $result['b']);
        $this->assertSame('08:05:09', $result['c']);
    }

    // Zusage 7
    public function testZeroDateEmptyAndUnparsableBecomeNull(): void
    {
        $payload = [
            'a' => '0000-00-00', 'b' => '0000-00-00 00:00:00', 'c' => '', 'd' => 'garbage',
            'e' => '0000-00-00', 'f' => '0000-00-00 00:00:00', 'g' => '', 'h' => 'garbage', 'i' => 'garbage',
        ];
        $map = [
            'a' => 'date-time', 'b' => 'date-time', 'c' => 'date-time', 'd' => 'date-time',
            'e' => 'date', 'f' => 'date', 'g' => 'date', 'h' => 'date', 'i' => 'time',
        ];

        $result = ($this->projectWire)($payload, $map);

        foreach (array_keys($payload) as $key) {
            $this->assertNull($result[$key], $key);
        }
    }

    public function testNonStringNonDateValueIsUnchanged(): void
    {
        $result = ($this->projectWire)(['a' => 42, 'b' => 1.5, 'c' => true], ['a' => 'date', 'b' => 'date-time', 'c' => 'time']);

        $this->assertSame(['a' => 42, 'b' => 1.5, 'c' => true], $result);
    }

    // Zusage 8
    public function testNestedMapAppliesToAssociativeChild(): void
    {
        $result = ($this->projectWire)(
            ['name' => 'x', 'address' => ['valid_from' => '2024-01-15 10:00:00', 'street' => 's']],
            ['address' => ['valid_from' => 'date']]
        );

        $this->assertSame(['valid_from' => '2024-01-15', 'street' => 's'], $result['address']);
    }

    public function testNestedMapAppliesToEveryListElement(): void
    {
        $result = ($this->projectWire)(
            ['items' => [['at' => '2024-01-15 10:00:00'], ['at' => null], ['at' => '0000-00-00 00:00:00']]],
            ['items' => ['at' => 'date-time']]
        );

        $this->assertSame('2024-01-15T10:00:00+00:00', $result['items'][0]['at']);
        $this->assertNull($result['items'][1]['at']);
        $this->assertNull($result['items'][2]['at']);
    }

    public function testChildWithoutMapStaysUnchanged(): void
    {
        $child = ['at' => '2024-01-15 10:00:00'];

        $result = ($this->projectWire)(['child' => $child, 'list' => [$child]], ['other' => 'date']);

        $this->assertSame($child, $result['child']);
        $this->assertSame([$child], $result['list']);
    }
}
