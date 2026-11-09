<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use JardisSupport\Data\Handler\ProjectWire;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testDateTimeObjectForeignZoneIsConvertedToUtc(): void
    {
        $dt = new DateTime('2024-01-15 14:30:45', new DateTimeZone('Europe/Berlin'));

        $result = ($this->projectWire)(['at' => $dt], ['at' => 'date-time']);

        $this->assertSame('2024-01-15T13:30:45+00:00', $result['at']);
    }

    public function testDateTimeImmutableForeignZoneIsConvertedToUtc(): void
    {
        $dt = new DateTimeImmutable('2024-07-01 09:00:00', new DateTimeZone('America/New_York'));

        $result = ($this->projectWire)(['at' => $dt], ['at' => 'date-time']);

        $this->assertSame('2024-07-01T13:00:00+00:00', $result['at']);
    }

    public function testConvertsForeignZoneToUtc(): void
    {
        $dt = new DateTimeImmutable('2024-03-10 08:00:00', new DateTimeZone('+05:30'));

        $result = ($this->projectWire)(['at' => $dt], ['at' => 'date-time']);

        $this->assertSame('2024-03-10T02:30:00+00:00', $result['at']);
    }

    public function testDoesNotMutateTheCallersDateTime(): void
    {
        $dt = new DateTime('2024-01-15 14:30:45', new DateTimeZone('Europe/Berlin'));

        ($this->projectWire)(['at' => $dt], ['at' => 'date-time']);

        $this->assertSame('Europe/Berlin', $dt->getTimezone()->getName());
        $this->assertSame('2024-01-15 14:30:45', $dt->format('Y-m-d H:i:s'));
    }

    public function testDateAndTimeObjectsKeepTheirWallClock(): void
    {
        $dt = new DateTimeImmutable('2024-01-15 23:30:15', new DateTimeZone('Asia/Tokyo'));

        $result = ($this->projectWire)(['d' => $dt, 't' => $dt], ['d' => 'date', 't' => 'time']);

        $this->assertSame('2024-01-15', $result['d']);
        $this->assertSame('23:30:15', $result['t']);
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

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function booleanSourceProvider(): array
    {
        return [
            'int one' => [1, true],
            'int zero' => [0, false],
            'string one' => ['1', true],
            'string zero' => ['0', false],
            'bool true' => [true, true],
            'bool false' => [false, false],
        ];
    }

    #[DataProvider('booleanSourceProvider')]
    public function testBooleanFormatYieldsJsonBoolean(mixed $source, bool $expected): void
    {
        $result = ($this->projectWire)(['flag' => $source], ['flag' => 'boolean']);

        $this->assertSame($expected, $result['flag']);
        $this->assertSame($expected ? '{"flag":true}' : '{"flag":false}', json_encode($result));
    }

    public function testBooleanFormatKeepsNullAsNull(): void
    {
        $result = ($this->projectWire)(['flag' => null], ['flag' => 'boolean']);

        $this->assertNull($result['flag']);
        $this->assertSame('{"flag":null}', json_encode($result));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidBooleanSourceProvider(): array
    {
        return [
            'int two' => [2],
            'negative int' => [-1],
            'float' => [1.5],
            'text' => ['yes'],
            'empty string' => [''],
            'array' => [[1]],
        ];
    }

    #[DataProvider('invalidBooleanSourceProvider')]
    public function testBooleanFormatNeverCoercesInvalidValueToFalse(mixed $source): void
    {
        $result = ($this->projectWire)(['flag' => $source], ['flag' => 'boolean']);

        $this->assertNull($result['flag']);
    }

    public function testBooleanFormatAppliesInNestedMapAndLists(): void
    {
        $result = ($this->projectWire)(
            ['items' => [['active' => 1, 'at' => '2024-01-15 10:00:00'], ['active' => '0', 'at' => null]]],
            ['items' => ['active' => 'boolean', 'at' => 'date-time']]
        );

        $this->assertTrue($result['items'][0]['active']);
        $this->assertFalse($result['items'][1]['active']);
        $this->assertSame('2024-01-15T10:00:00+00:00', $result['items'][0]['at']);
    }

    public function testBooleanFormatLeavesUnmappedKeysAndOtherFormatsUntouched(): void
    {
        $result = ($this->projectWire)(
            ['flag' => 1, 'count' => 1, 'on' => '2024-01-15 10:00:00'],
            ['flag' => 'boolean', 'on' => 'date']
        );

        $this->assertTrue($result['flag']);
        $this->assertSame(1, $result['count']);
        $this->assertSame('2024-01-15', $result['on']);
    }
}
