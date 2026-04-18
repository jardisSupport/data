<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\GenerateUuid5;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GenerateUuid5 handler.
 */
class GenerateUuid5Test extends TestCase
{
    private GenerateUuid5 $generateUuid5;

    protected function setUp(): void
    {
        $this->generateUuid5 = new GenerateUuid5();
    }

    public function testGeneratesValidUuid5Format(): void
    {
        $uuid = ($this->generateUuid5)('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'example.com');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGeneratesCorrectLength(): void
    {
        $uuid = ($this->generateUuid5)('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'test');

        $this->assertSame(36, strlen($uuid));
    }

    public function testDeterministic(): void
    {
        $namespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $name = 'hello-world';

        $uuid1 = ($this->generateUuid5)($namespace, $name);
        $uuid2 = ($this->generateUuid5)($namespace, $name);

        $this->assertSame($uuid1, $uuid2);
    }

    public function testDifferentNamesProduceDifferentUuids(): void
    {
        $namespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

        $uuid1 = ($this->generateUuid5)($namespace, 'name-a');
        $uuid2 = ($this->generateUuid5)($namespace, 'name-b');

        $this->assertNotSame($uuid1, $uuid2);
    }

    public function testDifferentNamespacesProduceDifferentUuids(): void
    {
        $name = 'same-name';

        $uuid1 = ($this->generateUuid5)('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $name);
        $uuid2 = ($this->generateUuid5)('6ba7b811-9dad-11d1-80b4-00c04fd430c8', $name);

        $this->assertNotSame($uuid1, $uuid2);
    }

    public function testVersionBitIsSet(): void
    {
        $uuid = ($this->generateUuid5)('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'test');

        $this->assertSame('5', $uuid[14]);
    }

    public function testVariantBitIsSet(): void
    {
        $uuid = ($this->generateUuid5)('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'test');
        $variantChar = $uuid[19];

        $this->assertContains($variantChar, ['8', '9', 'a', 'b']);
    }

    public function testKnownVector(): void
    {
        // RFC 4122 Appendix B: UUID v5 for "python.org" in DNS namespace
        $dnsNamespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $uuid = ($this->generateUuid5)($dnsNamespace, 'python.org');

        $this->assertSame('886313e1-3b8a-5372-9b90-0c9aee199e5d', $uuid);
    }
}
