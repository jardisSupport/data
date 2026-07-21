<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\GenerateNanoId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GenerateNanoId handler.
 */
class GenerateNanoIdTest extends TestCase
{
    private GenerateNanoId $generateNanoId;

    protected function setUp(): void
    {
        $this->generateNanoId = new GenerateNanoId();
    }

    public function testGeneratesDefaultLength(): void
    {
        $id = ($this->generateNanoId)();

        $this->assertSame(21, strlen($id));
    }

    public function testGeneratesCustomLength(): void
    {
        $id = ($this->generateNanoId)(10);

        $this->assertSame(10, strlen($id));
    }

    public function testUsesDefaultAlphabet(): void
    {
        $id = ($this->generateNanoId)();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $id);
    }

    public function testUsesCustomAlphabet(): void
    {
        $id = ($this->generateNanoId)(30, '0123456789');

        $this->assertMatchesRegularExpression('/^[0-9]+$/', $id);
        $this->assertSame(30, strlen($id));
    }

    public function testGeneratesUniqueIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[] = ($this->generateNanoId)();
        }

        $this->assertSame(1000, count(array_unique($ids)));
    }

    public function testShortLength(): void
    {
        $id = ($this->generateNanoId)(1);

        $this->assertSame(1, strlen($id));
    }

    public function testHexAlphabet(): void
    {
        $id = ($this->generateNanoId)(32, '0123456789abcdef');

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $id);
        $this->assertSame(32, strlen($id));
    }
}
