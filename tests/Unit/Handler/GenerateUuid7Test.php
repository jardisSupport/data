<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\GenerateUuid7;
use PHPUnit\Framework\TestCase;

/**
 * Tests for UUID v7 generator.
 */
class GenerateUuid7Test extends TestCase
{
    private GenerateUuid7 $generateUuid7;

    protected function setUp(): void
    {
        $this->generateUuid7 = new GenerateUuid7();
    }

    public function testGeneratesValidFormat(): void
    {
        $uuid = ($this->generateUuid7)();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testVersionBitIs7(): void
    {
        $uuid = ($this->generateUuid7)();

        $parts = explode('-', $uuid);
        $this->assertStringStartsWith('7', $parts[2]);
    }

    public function testVariantBitIsCorrect(): void
    {
        $uuid = ($this->generateUuid7)();

        $parts = explode('-', $uuid);
        $variantChar = $parts[3][0];
        $this->assertContains($variantChar, ['8', '9', 'a', 'b']);
    }

    public function testUniqueness(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = ($this->generateUuid7)();
        }

        $unique = array_unique($uuids);
        $this->assertCount(100, $unique, 'All 100 generated UUIDs should be unique');
    }

    public function testSortability(): void
    {
        $uuids = [];
        for ($i = 0; $i < 10; $i++) {
            $uuids[] = ($this->generateUuid7)();
            // Small delay to ensure different timestamps
            usleep(1000);
        }

        $sorted = $uuids;
        sort($sorted);

        $this->assertSame($sorted, $uuids, 'UUIDs generated in sequence should be lexicographically sorted');
    }

    public function testMonotonicCounterWithinSameMillisecond(): void
    {
        // Generate many UUIDs rapidly — they will likely share the same millisecond
        $uuids = [];
        for ($i = 0; $i < 50; $i++) {
            $uuids[] = ($this->generateUuid7)();
        }

        $sorted = $uuids;
        sort($sorted);

        $this->assertSame(
            $sorted,
            $uuids,
            'UUIDs generated within the same millisecond should be sorted via monotonic counter'
        );
    }

    public function testConsistentLength(): void
    {
        $uuid = ($this->generateUuid7)();

        $this->assertSame(36, strlen($uuid));
    }

    public function testMultipleCallsReturnDifferentValues(): void
    {
        $uuid1 = ($this->generateUuid7)();
        $uuid2 = ($this->generateUuid7)();

        $this->assertNotSame($uuid1, $uuid2);
    }

    public function testVersionBitConsistentAcrossMultipleCalls(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $uuid = ($this->generateUuid7)();
            $parts = explode('-', $uuid);
            $this->assertStringStartsWith('7', $parts[2], "UUID $uuid should have version 7");
        }
    }

    public function testVariantBitConsistentAcrossMultipleCalls(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $uuid = ($this->generateUuid7)();
            $parts = explode('-', $uuid);
            $variantChar = $parts[3][0];
            $this->assertContains(
                $variantChar,
                ['8', '9', 'a', 'b'],
                "UUID $uuid should have correct variant bits"
            );
        }
    }

    public function testTimestampIsEmbedded(): void
    {
        $before = (int) (microtime(true) * 1000);
        $uuid = ($this->generateUuid7)();
        $after = (int) (microtime(true) * 1000);

        // Extract timestamp from first 48 bits (first 12 hex chars without hyphens)
        $hex = str_replace('-', '', $uuid);
        $timestampHex = substr($hex, 0, 12);
        $timestamp = (int) hexdec($timestampHex);

        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }
}
