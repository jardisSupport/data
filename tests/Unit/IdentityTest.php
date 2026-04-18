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
}
