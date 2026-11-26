<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit;

use JardisSupport\Data\Identity;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Identity.
 */
class IdentityTest extends TestCase
{
    private Identity $identity;

    protected function setUp(): void
    {
        $this->identity = new Identity();
    }

    public function testGenerateUuid4(): void
    {
        $uuid = $this->identity->generateUuid4();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateUuid7(): void
    {
        $uuid = $this->identity->generateUuid7();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateUuid5(): void
    {
        $uuid = $this->identity->generateUuid5(
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'example.com'
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateNanoId(): void
    {
        $id = $this->identity->generateNanoId();

        $this->assertSame(21, strlen($id));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $id);
    }

    public function testGenerateNanoIdCustomLength(): void
    {
        $id = $this->identity->generateNanoId(10);

        $this->assertSame(10, strlen($id));
    }

    /**
     * Contract A9-U1: 20,000 UUID v7, each from a fresh Identity object, keep the
     * order of their creation (0 misplacements).
     */
    public function testUuid7KeepsCreationOrderAcrossFreshIdentityObjects(): void
    {
        $uuids = [];
        for ($i = 0; $i < 20000; $i++) {
            $uuids[] = (new Identity())->generateUuid7();
        }

        $this->assertSame(0, $this->countMisplacements($uuids));
    }

    /**
     * Contract A9-U2: two Identity objects used alternately within the same
     * millisecond produce strictly ascending, unique UUID v7 of unchanged form.
     */
    public function testUuid7StrictlyAscendingForTwoAlternatingIdentityObjects(): void
    {
        $first = new Identity();
        $second = new Identity();
        $uuids = [];
        for ($i = 0; $i < 1000; $i++) {
            $uuids[] = $first->generateUuid7();
            $uuids[] = $second->generateUuid7();
        }

        $this->assertSame(0, $this->countMisplacements($uuids));
        $this->assertCount(2000, array_unique($uuids));
        foreach ($uuids as $uuid) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $uuid
            );
        }
    }

    /**
     * Counts positions whose UUID is not strictly greater than its predecessor.
     *
     * @param array<int, string> $uuids
     */
    private function countMisplacements(array $uuids): int
    {
        $misplacements = 0;
        for ($i = 1, $count = count($uuids); $i < $count; $i++) {
            if (strcmp($uuids[$i], $uuids[$i - 1]) <= 0) {
                $misplacements++;
            }
        }

        return $misplacements;
    }
}
