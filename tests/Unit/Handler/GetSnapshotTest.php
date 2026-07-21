<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\GetSnapshot;
use PHPUnit\Framework\TestCase;

class GetSnapshotTest extends TestCase
{
    private GetSnapshot $getSnapshot;

    protected function setUp(): void
    {
        $this->getSnapshot = new GetSnapshot();
    }

    public function testGetsSnapshotViaGetterMethod(): void
    {
        $entity = new class {
            private array $__snapshot = ['name' => 'John', 'age' => 30];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $result = $this->getSnapshot->__invoke($entity);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testGetsSnapshotViaReflectionWhenNoGetter(): void
    {
        $entity = new class {
            private array $__snapshot = ['name' => 'Jane'];
        };

        $result = $this->getSnapshot->__invoke($entity);

        $this->assertEquals(['name' => 'Jane'], $result);
    }

    public function testReturnsEmptyArrayWhenNoSnapshotProperty(): void
    {
        $entity = new class {
            private string $name = 'John';
        };

        $result = $this->getSnapshot->__invoke($entity);

        $this->assertEquals([], $result);
    }

    public function testHandlesRepeatedCalls(): void
    {
        $entity = new class {
            private array $__snapshot = ['initial' => 'value'];
        };

        // Multiple calls return consistent results
        $result1 = $this->getSnapshot->__invoke($entity);
        $result2 = $this->getSnapshot->__invoke($entity);

        $this->assertEquals($result1, $result2);
    }

    public function testHandlesNullSnapshotValue(): void
    {
        $entity = new class {
            private ?array $__snapshot = null;
        };

        $result = $this->getSnapshot->__invoke($entity);

        $this->assertEquals([], $result);
    }

    public function testPrefersGetterOverReflection(): void
    {
        $entity = new class {
            private array $__snapshot = ['reflection' => 'value'];

            public function getSnapshot(): array
            {
                return ['getter' => 'value'];
            }
        };

        $result = $this->getSnapshot->__invoke($entity);

        $this->assertEquals(['getter' => 'value'], $result);
    }
}
