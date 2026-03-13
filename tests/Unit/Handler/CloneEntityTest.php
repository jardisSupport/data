<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\CloneEntity;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\SetSnapshot;
use PHPUnit\Framework\TestCase;

class CloneEntityTest extends TestCase
{
    private CloneEntity $cloneEntity;

    protected function setUp(): void
    {
        $this->cloneEntity = new CloneEntity(
            new GetSnapshot(),
            new SetSnapshot()
        );
    }

    public function testClonesSimpleEntity(): void
    {
        $entity = new class {
            private string $name = 'John';
            private int $age = 30;

            public function getName(): string
            {
                return $this->name;
            }

            public function getAge(): int
            {
                return $this->age;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertEquals($entity->getName(), $clone->getName());
        $this->assertEquals($entity->getAge(), $clone->getAge());
    }

    public function testClonesEntityWithNullableProperties(): void
    {
        $entity = new class {
            private ?string $name = null;
            private ?int $age = null;

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getAge(): ?int
            {
                return $this->age;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertNull($clone->getName());
        $this->assertNull($clone->getAge());
    }

    public function testClonesEntityWithArray(): void
    {
        $entity = new class {
            private array $tags = ['php', 'testing'];
            private array $metadata = ['key' => 'value'];

            public function getTags(): array
            {
                return $this->tags;
            }

            public function getMetadata(): array
            {
                return $this->metadata;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertEquals($entity->getTags(), $clone->getTags());
        $this->assertEquals($entity->getMetadata(), $clone->getMetadata());
    }

    public function testClonesEntityWithNestedObject(): void
    {
        $nested = new class {
            public string $value = 'nested';
        };

        $entity = new class($nested) {
            private object $nested;

            public function __construct(object $nested)
            {
                $this->nested = $nested;
            }

            public function getNested(): object
            {
                return $this->nested;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertNotSame($entity->getNested(), $clone->getNested());
        $this->assertEquals($entity->getNested()->value, $clone->getNested()->value);
    }

    public function testClonesEntityWithDateTime(): void
    {
        $entity = new class {
            private DateTime $createdAt;

            public function __construct()
            {
                $this->createdAt = new DateTime('2024-01-15 14:30:45');
            }

            public function getCreatedAt(): DateTime
            {
                return $this->createdAt;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertNotSame($entity->getCreatedAt(), $clone->getCreatedAt());
        $this->assertEquals(
            $entity->getCreatedAt()->format('Y-m-d H:i:s'),
            $clone->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function testClonesEntityWithSnapshot(): void
    {
        $entity = new class {
            private string $name = 'John';
            private array $__snapshot = ['name' => 'Jane'];

            public function getName(): string
            {
                return $this->name;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertEquals($entity->getName(), $clone->getName());
        $this->assertEquals($entity->getSnapshot(), $clone->getSnapshot());
    }

    public function testClonesEntityWithUninitializedProperties(): void
    {
        $entity = new class {
            private string $initialized = 'value';
            private string $uninitialized;

            public function getInitialized(): string
            {
                return $this->initialized;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertEquals($entity->getInitialized(), $clone->getInitialized());
    }

    public function testClonesEntityWithArrayOfObjects(): void
    {
        $obj1 = new class {
            public string $value = 'first';
        };
        $obj2 = new class {
            public string $value = 'second';
        };

        $entity = new class($obj1, $obj2) {
            private array $items;

            public function __construct(object $obj1, object $obj2)
            {
                $this->items = [$obj1, $obj2];
            }

            public function getItems(): array
            {
                return $this->items;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertCount(2, $clone->getItems());
        $this->assertNotSame($entity->getItems()[0], $clone->getItems()[0]);
        $this->assertNotSame($entity->getItems()[1], $clone->getItems()[1]);
        $this->assertEquals($entity->getItems()[0]->value, $clone->getItems()[0]->value);
        $this->assertEquals($entity->getItems()[1]->value, $clone->getItems()[1]->value);
    }

    public function testDeepClonesNestedObjectsRecursively(): void
    {
        $inner = new class {
            public string $city = 'Berlin';
        };

        $middle = new class($inner) {
            private object $inner;

            public function __construct(object $inner)
            {
                $this->inner = $inner;
            }

            public function getInner(): object
            {
                return $this->inner;
            }
        };

        $entity = new class($middle) {
            private object $middle;

            public function __construct(object $middle)
            {
                $this->middle = $middle;
            }

            public function getMiddle(): object
            {
                return $this->middle;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        // Middle-level object should be a different instance
        $this->assertNotSame($entity->getMiddle(), $clone->getMiddle());

        // Inner-level object should also be a different instance (deep clone)
        $this->assertNotSame(
            $entity->getMiddle()->getInner(),
            $clone->getMiddle()->getInner()
        );

        // Values should be equal
        $this->assertEquals(
            $entity->getMiddle()->getInner()->city,
            $clone->getMiddle()->getInner()->city
        );

        // Modifying the clone's inner object should not affect the original
        $clone->getMiddle()->getInner()->city = 'Munich';
        $this->assertEquals('Berlin', $entity->getMiddle()->getInner()->city);
        $this->assertEquals('Munich', $clone->getMiddle()->getInner()->city);
    }

    public function testDeepClonePreservesSnapshotOnNestedObjects(): void
    {
        $nested = new class {
            private string $city = 'Berlin';
            private array $__snapshot = ['city' => 'Berlin'];

            public function getCity(): string
            {
                return $this->city;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $entity = new class($nested) {
            private object $address;
            private array $__snapshot = ['address' => 'ref'];

            public function __construct(object $address)
            {
                $this->address = $address;
            }

            public function getAddress(): object
            {
                return $this->address;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        // Top-level snapshot preserved
        $this->assertEquals(['address' => 'ref'], $clone->getSnapshot());

        // Nested object snapshot preserved
        $this->assertEquals(['city' => 'Berlin'], $clone->getAddress()->getSnapshot());
    }
}
