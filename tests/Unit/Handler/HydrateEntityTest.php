<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateEntity;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HydrateEntity handler.
 */
class HydrateEntityTest extends TestCase
{
    private HydrateEntity $hydrateEntity;

    protected function setUp(): void
    {
        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $this->hydrateEntity = new HydrateEntity(
            new SetPropertyValue($columnNameToPropertyName, new TypeCaster()),
            new SetSnapshot(),
            new GetSnapshot(),
            new GetPropertyValue($columnNameToPropertyName),
            $columnNameToPropertyName
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

    public function testHydratesNestedArrayProperties(): void
    {
        $entity = new class {
            private array $config;
            private array $__snapshot = [];

            public function getConfig(): array
            {
                return $this->config;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'config' => ['db' => ['host' => 'localhost', 'port' => 5432]],
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals(['db' => ['host' => 'localhost', 'port' => 5432]], $entity->getConfig());
        $this->assertArrayHasKey('config', $entity->getSnapshot());
    }

    public function testHydratesMatrixArrayProperties(): void
    {
        $entity = new class {
            private array $matrix;
            private array $__snapshot = [];

            public function getMatrix(): array
            {
                return $this->matrix;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'matrix' => [[1, 2, 3], [4, 5, 6]],
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertEquals([[1, 2, 3], [4, 5, 6]], $entity->getMatrix());
        $this->assertArrayHasKey('matrix', $entity->getSnapshot());
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

    public function testSnapshotContainsTypedValuesNotRawStrings(): void
    {
        $entity = new class {
            private int $age;
            private float $score;
            private array $__snapshot = [];

            public function getAge(): int
            {
                return $this->age;
            }

            public function getScore(): float
            {
                return $this->score;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'age' => '25',
            'score' => '9.5',
        ];

        $this->hydrateEntity->__invoke($entity, $data);

        $snapshot = $entity->getSnapshot();
        $this->assertSame(25, $snapshot['age']);
        $this->assertSame(9.5, $snapshot['score']);
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

    public function testSkipsRelationDataWithValueBasedDetection(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
            ],
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
            ],
        ];

        // Must NOT throw TypeError — non-scalar values are skipped
        $this->hydrateEntity->__invoke($counter, $data);

        $this->assertSame(1, $counter->getId());
        $this->assertSame('abc-123', $counter->getIdentifier());
        $this->assertSame('CNT-001', $counter->getCounterNumber());
        $this->assertSame('CLIENT-001', $counter->getClientIdentifier());

        // Relation properties are NOT touched (remain default)
        $this->assertNull($counter->getCounterGateway());
        $this->assertEmpty($counter->getCounterRegister());
    }

    public function testSnapshotExcludesNonScalarValues(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
            ],
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
            ],
        ];

        $this->hydrateEntity->__invoke($counter, $data);

        $snapshot = $counter->getSnapshot();

        $this->assertArrayHasKey('id', $snapshot);
        $this->assertArrayHasKey('identifier', $snapshot);
        $this->assertArrayHasKey('counter_number', $snapshot);
        $this->assertArrayHasKey('client_identifier', $snapshot);

        // Non-scalar values must NOT be in snapshot
        $this->assertArrayNotHasKey('counter_gateway', $snapshot);
        $this->assertArrayNotHasKey('counter_register', $snapshot);
    }

    public function testHydratesBackedEnumProperty(): void
    {
        $entity = new class {
            private ?GatewayType $type = null;
            private array $__snapshot = [];

            public function getType(): ?GatewayType
            {
                return $this->type;
            }

            public function setType(GatewayType $type): void
            {
                $this->type = $type;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = ['type' => 'ELECTRICITY'];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertSame(GatewayType::Electricity, $entity->getType());

        // Snapshot must contain scalar value, not enum object
        $snapshot = $entity->getSnapshot();
        $this->assertSame('ELECTRICITY', $snapshot['type']);
    }

    public function testSkipsArrayForUnionTypeProperty(): void
    {
        $entity = new class {
            private int|string $value = 0;
            private array $__snapshot = [];

            public function getValue(): int|string
            {
                return $this->value;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'value' => ['some', 'array'],
        ];

        // Must NOT set array on int|string property — skip silently
        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertSame(0, $entity->getValue());
        $this->assertEmpty($entity->getSnapshot());
    }

    public function testSnapshotContainsDateTimeAsString(): void
    {
        $entity = new class {
            private ?\DateTimeImmutable $activeFrom = null;
            private array $__snapshot = [];

            public function setActiveFrom(\DateTimeImmutable $activeFrom): void
            {
                $this->activeFrom = $activeFrom;
            }

            public function getActiveFrom(): ?\DateTimeImmutable
            {
                return $this->activeFrom;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = ['active_from' => '2024-01-15 14:30:45'];

        $this->hydrateEntity->__invoke($entity, $data);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getActiveFrom());

        // Snapshot must contain string, not DateTimeImmutable object
        $snapshot = $entity->getSnapshot();
        $this->assertIsString($snapshot['active_from']);
        $this->assertSame('2024-01-15 14:30:45', $snapshot['active_from']);
    }

    public function testApplySetsPropertiesWithoutUpdatingSnapshot(): void
    {
        $entity = new class {
            private ?string $name = null;
            private ?int $age = null;
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getAge(): ?int
            {
                return $this->age;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        // First hydrate to establish snapshot baseline
        $this->hydrateEntity->__invoke($entity, ['name' => 'John', 'age' => 30]);
        $this->assertSame(['name' => 'John', 'age' => 30], $entity->getSnapshot());

        // Apply changes — properties change, snapshot stays
        $this->hydrateEntity->__invoke($entity, ['name' => 'Jane'], false);

        $this->assertSame('Jane', $entity->getName());
        $this->assertSame(['name' => 'John', 'age' => 30], $entity->getSnapshot());
    }

    public function testApplyOnFreshEntityLeavesSnapshotEmpty(): void
    {
        $entity = new class {
            private ?string $name = null;
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $this->hydrateEntity->__invoke($entity, ['name' => 'John'], false);

        $this->assertSame('John', $entity->getName());
        $this->assertSame([], $entity->getSnapshot());
    }

    public function testApplySkipsRelationDataSameAsHydrate(): void
    {
        $counter = new Counter();

        // Hydrate first to set baseline
        $this->hydrateEntity->__invoke($counter, [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
        ]);

        // Apply with mixed scalar + relation data
        $this->hydrateEntity->__invoke($counter, [
            'counter_number' => 'CNT-002',
            'counter_gateway' => ['id' => 3, 'counter_id' => 1],
            'counter_register' => [['id' => 10]],
        ], false);

        // Scalar property updated
        $this->assertSame('CNT-002', $counter->getCounterNumber());

        // Relations untouched
        $this->assertNull($counter->getCounterGateway());
        $this->assertEmpty($counter->getCounterRegister());

        // Snapshot still from original hydrate — counter_number still 'CNT-001'
        $snapshot = $counter->getSnapshot();
        $this->assertSame('CNT-001', $snapshot['counter_number']);
    }
}
