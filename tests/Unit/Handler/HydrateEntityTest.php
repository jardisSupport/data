<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\HydrateEntity;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\SetSnapshot;
use PHPUnit\Framework\TestCase;

class HydrateEntityTest extends TestCase
{
    private HydrateEntity $hydrateEntity;

    protected function setUp(): void
    {
        $this->hydrateEntity = new HydrateEntity(
            new SetPropertyValue(new ColumnNameToPropertyName()),
            new SetSnapshot()
        );
    }

    public function testHydratesSimpleEntity(): void
    {
        $entity = new class {
            private string $name;
            private int $age;

            public function getName(): string
            {
                return $this->name;
            }

            public function getAge(): int
            {
                return $this->age;
            }
        };

        $data = [
            'name' => 'John',
            'age' => 30,
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals('John', $entity->getName());
        $this->assertEquals(30, $entity->getAge());
    }

    public function testHydratesWithSnakeCaseColumns(): void
    {
        $entity = new class {
            private string $firstName;
            private string $lastName;

            public function getFirstName(): string
            {
                return $this->firstName;
            }

            public function getLastName(): string
            {
                return $this->lastName;
            }
        };

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals('John', $entity->getFirstName());
        $this->assertEquals('Doe', $entity->getLastName());
    }

    public function testHydratesWithNullValues(): void
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

        $data = [
            'name' => null,
            'age' => null,
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertNull($entity->getName());
        $this->assertNull($entity->getAge());
    }

    public function testHydratesArrayProperties(): void
    {
        $entity = new class {
            private array $tags;
            private array $metadata;

            public function getTags(): array
            {
                return $this->tags;
            }

            public function getMetadata(): array
            {
                return $this->metadata;
            }
        };

        $data = [
            'tags' => ['php', 'testing'],
            'metadata' => ['key' => 'value'],
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals(['php', 'testing'], $entity->getTags());
        $this->assertEquals(['key' => 'value'], $entity->getMetadata());
    }

    public function testHydratesDateTimeProperty(): void
    {
        $entity = new class {
            private DateTime $createdAt;

            public function getCreatedAt(): DateTime
            {
                return $this->createdAt;
            }
        };

        $data = [
            'created_at' => new DateTime('2024-01-15 14:30:45'),
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertInstanceOf(DateTime::class, $entity->getCreatedAt());
        $this->assertEquals('2024-01-15 14:30:45', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testHydratesBooleanProperties(): void
    {
        $entity = new class {
            private bool $isActive;
            private bool $isDeleted;

            public function isActive(): bool
            {
                return $this->isActive;
            }

            public function isDeleted(): bool
            {
                return $this->isDeleted;
            }
        };

        $data = [
            'is_active' => true,
            'is_deleted' => false,
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertTrue($entity->isActive());
        $this->assertFalse($entity->isDeleted());
    }

    public function testSetsSnapshotAfterHydration(): void
    {
        $entity = new class {
            private string $name;
            private array $__snapshot = [];

            public function getName(): string
            {
                return $this->name;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'name' => 'John',
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals('John', $entity->getName());
        $this->assertEquals($data, $entity->getSnapshot());
    }

    public function testHydratesWithEmptyData(): void
    {
        $entity = new class {
            private string $name = 'default';
            private array $__snapshot = [];

            public function getName(): string
            {
                return $this->name;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals('default', $entity->getName());
        $this->assertEquals([], $entity->getSnapshot());
    }

    public function testHydratesMultipleProperties(): void
    {
        $entity = new class {
            private string $name;
            private int $age;
            private string $email;
            private bool $active;

            public function getName(): string
            {
                return $this->name;
            }

            public function getAge(): int
            {
                return $this->age;
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function isActive(): bool
            {
                return $this->active;
            }
        };

        $data = [
            'name' => 'John Doe',
            'age' => 35,
            'email' => 'john@example.com',
            'active' => true,
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals('John Doe', $entity->getName());
        $this->assertEquals(35, $entity->getAge());
        $this->assertEquals('john@example.com', $entity->getEmail());
        $this->assertTrue($entity->isActive());
    }
}
