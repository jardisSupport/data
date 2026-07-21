<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\GenerateUuid4;
use PHPUnit\Framework\TestCase;

/**
 * Tests for UUID v4 generator.
 */
class GenerateUuid4Test extends TestCase
{
    private GenerateUuid4 $generateUuid4;

    protected function setUp(): void
    {
        $this->generateUuid4 = new GenerateUuid4();
    }

    public function testGeneratesValidFormat(): void
    {
        $uuid = ($this->generateUuid4)();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testVersionBitIs4(): void
    {
        $uuid = ($this->generateUuid4)();

        // The 13th character (position 14 in string, after hyphens) is the version
        $parts = explode('-', $uuid);
        $this->assertStringStartsWith('4', $parts[2]);
    }

    public function testVariantBitIsCorrect(): void
    {
        $uuid = ($this->generateUuid4)();

        // The first character of the 4th group must be 8, 9, a, or b
        $parts = explode('-', $uuid);
        $variantChar = $parts[3][0];
        $this->assertContains($variantChar, ['8', '9', 'a', 'b']);
    }

    public function testUniqueness(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = ($this->generateUuid4)();
        }

        $unique = array_unique($uuids);
        $this->assertCount(100, $unique, 'All 100 generated UUIDs should be unique');
    }

    public function testConsistentLength(): void
    {
        $uuid = ($this->generateUuid4)();

        $this->assertSame(36, strlen($uuid));
    }

    public function testMultipleCallsReturnDifferentValues(): void
    {
        $uuid1 = ($this->generateUuid4)();
        $uuid2 = ($this->generateUuid4)();

        $this->assertNotSame($uuid1, $uuid2);
    }

    public function testVersionBitConsistentAcrossMultipleCalls(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $uuid = ($this->generateUuid4)();
            $parts = explode('-', $uuid);
            $this->assertStringStartsWith('4', $parts[2], "UUID $uuid should have version 4");
        }
    }

    public function testVariantBitConsistentAcrossMultipleCalls(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $uuid = ($this->generateUuid4)();
            $parts = explode('-', $uuid);
            $variantChar = $parts[3][0];
            $this->assertContains(
                $variantChar,
                ['8', '9', 'a', 'b'],
                "UUID $uuid should have correct variant bits"
            );
        }
    }
}
