<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\SetSnapshot;
use PHPUnit\Framework\TestCase;

class SetSnapshotTest extends TestCase
{
    private SetSnapshot $setSnapshot;

    protected function setUp(): void
    {
        $this->setSnapshot = new SetSnapshot();
    }

    public function testSetsSnapshotViaReflection(): void
    {
        $entity = new class {
            private array $__snapshot = [];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = ['name' => 'John', 'age' => 30];
        $this->setSnapshot->__invoke($entity, $data);

        $this->assertEquals($data, $entity->getSnapshot());
    }

    public function testOverwritesExistingSnapshot(): void
    {
        $entity = new class {
            private array $__snapshot = ['old' => 'data'];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $newData = ['new' => 'data'];
        $this->setSnapshot->__invoke($entity, $newData);

        $this->assertEquals($newData, $entity->getSnapshot());
    }

    public function testDoesNothingWhenNoSnapshotProperty(): void
    {
        $entity = new class {
            private string $name = 'John';

            public function getName(): string
            {
                return $this->name;
            }
        };

        // Should not throw exception
        $this->setSnapshot->__invoke($entity, ['name' => 'Jane']);

        // Entity should remain unchanged
        $this->assertEquals('John', $entity->getName());
    }

    public function testHandlesRepeatedCalls(): void
    {
        $entity = new class {
            private array $__snapshot = [];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        // Multiple calls work correctly, last write wins
        $this->setSnapshot->__invoke($entity, ['first' => 'call']);
        $this->setSnapshot->__invoke($entity, ['second' => 'call']);

        $this->assertEquals(['second' => 'call'], $entity->getSnapshot());
    }

    public function testSetsEmptySnapshot(): void
    {
        $entity = new class {
            private array $__snapshot = ['existing' => 'data'];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $this->setSnapshot->__invoke($entity, []);

        $this->assertEquals([], $entity->getSnapshot());
    }
}
