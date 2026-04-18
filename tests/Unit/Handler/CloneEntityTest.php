<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\CloneEntity;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CloneEntity handler (flat, entity-level only).
 */
class CloneEntityTest extends TestCase
{
    private CloneEntity $cloneEntity;

    protected function setUp(): void
    {
        $this->cloneEntity = new CloneEntity(
            new GetSnapshot(),
            new SetSnapshot(),
            new ColumnNameToPropertyName()
        );
    }

    public function testClonesSimpleEntity(): void
    {
        $entity = new class {
            private string $name = 'John';
            private int $age = 30;
            private array $__snapshot = ['name' => 'John', 'age' => 30];

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
            private array $__snapshot = ['name' => null, 'age' => null];

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

    public function testClonesEntityWithDateTime(): void
    {
        $entity = new class {
            private DateTime $createdAt;
            private array $__snapshot = ['created_at' => '2024-01-15 14:30:45'];

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

    public function testClonesEntityWithArrays(): void
    {
        $entity = new class {
            private array $tags = ['php', 'testing'];
            private array $metadata = ['key' => 'value'];
            private array $__snapshot = ['tags' => ['php', 'testing'], 'metadata' => ['key' => 'value']];

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

    public function testClonesEntityWithUninitializedProperties(): void
    {
        $entity = new class {
            private string $initialized = 'value';
            private string $uninitialized;
            private array $__snapshot = ['initialized' => 'value'];

            public function getInitialized(): string
            {
                return $this->initialized;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertEquals($entity->getInitialized(), $clone->getInitialized());
    }

    public function testFlatCloneSkipsRelationObjects(): void
    {
        $nested = new class {
            public string $value = 'nested';
        };

        $entity = new class($nested) {
            private ?int $id = null;
            private object $nested;
            private array $__snapshot = ['id' => 1];

            public function __construct(object $nested)
            {
                $this->id = 1;
                $this->nested = $nested;
            }

            public function getId(): ?int
            {
                return $this->id;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertSame(1, $clone->getId());
    }

    public function testClonesBackedEnumProperty(): void
    {
        $entity = new class {
            private GatewayType $type;
            private array $__snapshot = ['type' => 'ELECTRICITY'];

            public function __construct()
            {
                $this->type = GatewayType::Electricity;
            }

            public function getType(): GatewayType
            {
                return $this->type;
            }
        };

        $clone = $this->cloneEntity->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertSame(GatewayType::Electricity, $clone->getType());
    }
}
