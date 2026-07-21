<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use DateTimeImmutable;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\EntityToArray;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGateway;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for EntityToArray handler (flat, entity-level only).
 */
class EntityToArrayTest extends TestCase
{
    private EntityToArray $entityToArray;

    protected function setUp(): void
    {
        $this->entityToArray = new EntityToArray(
            new GetSnapshot(),
            new ColumnNameToPropertyName()
        );
    }

    public function testConvertsSimpleEntity(): void
    {
        $entity = new class {
            private string $name = 'John';
            private int $age = 30;
            private array $__snapshot = ['name' => 'John', 'age' => 30];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function testSkipsSnapshotProperty(): void
    {
        $entity = new class {
            private string $name = 'John';
            private array $__snapshot = ['name' => 'Jane'];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('__snapshot', $result);
    }

    public function testFormatsDateTimeToFullFormat(): void
    {
        $entity = new class {
            private DateTime $createdAt;
            private array $__snapshot = ['created_at' => '2024-01-15 14:30:45'];

            public function __construct()
            {
                $this->createdAt = new DateTime('2024-01-15 14:30:45');
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-01-15 14:30:45', $result['created_at']);
    }

    public function testHandlesNullValues(): void
    {
        $entity = new class {
            private ?string $name = null;
            private ?int $age = null;
            private array $__snapshot = ['name' => null, 'age' => null];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertNull($result['name']);
        $this->assertNull($result['age']);
    }

    public function testHandlesArrayValues(): void
    {
        $entity = new class {
            private array $tags = ['php', 'testing'];
            private array $metadata = ['key' => 'value'];
            private array $__snapshot = ['tags' => ['php', 'testing'], 'metadata' => ['key' => 'value']];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals(['php', 'testing'], $result['tags']);
        $this->assertEquals(['key' => 'value'], $result['metadata']);
    }

    public function testHandlesDateTimeInArray(): void
    {
        $entity = new class {
            private array $dates;
            private array $__snapshot = ['dates' => []];

            public function __construct()
            {
                $this->dates = [
                    new \DateTime('2024-01-15 10:00:00'),
                    new \DateTime('2024-01-16 11:00:00'),
                ];
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-01-15 10:00:00', $result['dates'][0]);
        $this->assertEquals('2024-01-16 11:00:00', $result['dates'][1]);
    }

    public function testFormatsDateTimeImmutableInArray(): void
    {
        $entity = new class {
            private array $dates;
            private array $__snapshot = ['dates' => []];

            public function __construct()
            {
                $this->dates = [
                    new \DateTimeImmutable('2024-01-15 10:00:00'),
                    new \DateTime('2024-01-16 11:00:00'),
                ];
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-01-15 10:00:00', $result['dates'][0]);
        $this->assertEquals('2024-01-16 11:00:00', $result['dates'][1]);
    }

    public function testFormatsNestedArraysRecursively(): void
    {
        $entity = new class {
            private array $matrix = [
                'row1' => [1, 2, 3],
                'row2' => [4, 5, 6],
            ];
            private array $__snapshot = ['matrix' => []];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals([1, 2, 3], $result['matrix']['row1']);
        $this->assertEquals([4, 5, 6], $result['matrix']['row2']);
    }

    public function testFormatsNullInArray(): void
    {
        $entity = new class {
            private array $values = ['a', null, 'c'];
            private array $__snapshot = ['values' => []];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals(['a', null, 'c'], $result['values']);
    }

    public function testSkipsUninitializedProperties(): void
    {
        $entity = new class {
            private string $initialized = 'value';
            private string $uninitialized;
            private array $__snapshot = ['initialized' => 'value', 'uninitialized' => null];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertArrayHasKey('initialized', $result);
        $this->assertArrayNotHasKey('uninitialized', $result);
    }

    public function testFormatsDateTimeImmutableToString(): void
    {
        $entity = new class {
            private DateTimeImmutable $createdAt;
            private array $__snapshot = ['created_at' => '2024-06-20 09:15:30'];

            public function __construct()
            {
                $this->createdAt = new DateTimeImmutable('2024-06-20 09:15:30');
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-06-20 09:15:30', $result['created_at']);
    }

    public function testIncludesPublicProperties(): void
    {
        $entity = new class {
            public string $publicName = 'Public';
            private string $privateName = 'Private';
            private array $__snapshot = ['public_name' => 'Public', 'private_name' => 'Private'];
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertArrayHasKey('public_name', $result);
        $this->assertEquals('Public', $result['public_name']);
        $this->assertArrayHasKey('private_name', $result);
        $this->assertEquals('Private', $result['private_name']);
    }

    public function testSkipsObjectPropertiesViaValueDetection(): void
    {
        $counter = new Counter();
        $reflection = new ReflectionClass($counter);

        // Set scalar properties
        $reflection->getProperty('id')->setValue($counter, 1);
        $reflection->getProperty('identifier')->setValue($counter, 'abc-123');
        $reflection->getProperty('counterNumber')->setValue($counter, 'CNT-001');
        $reflection->getProperty('clientIdentifier')->setValue($counter, 'CLIENT-001');

        // Set relation properties (should be excluded — not in snapshot)
        $gateway = new CounterGateway();
        $reflection->getProperty('counterGateway')->setValue($counter, $gateway);

        $reg = new CounterRegister();
        $reflection->getProperty('counterRegister')->setValue($counter, [$reg]);

        // Set snapshot with only DB column keys (no relation keys)
        $reflection->getProperty('__snapshot')->setValue($counter, [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
        ]);

        $result = $this->entityToArray->__invoke($counter);

        // Should include DB columns
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('identifier', $result);
        $this->assertArrayHasKey('counter_number', $result);
        $this->assertArrayHasKey('client_identifier', $result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('abc-123', $result['identifier']);

        // Should NOT include relation properties (objects/arrays of objects)
        $this->assertArrayNotHasKey('counter_gateway', $result);
        $this->assertArrayNotHasKey('counter_register', $result);
    }

    public function testSkipsEmptyCollectionOnHydratedEntity(): void
    {
        // Simulate hydrated entity — snapshot contains only DB column keys
        $counter = new Counter();
        $reflection = new ReflectionClass($counter);

        $reflection->getProperty('id')->setValue($counter, 1);
        $reflection->getProperty('identifier')->setValue($counter, 'abc-123');
        $reflection->getProperty('counterNumber')->setValue($counter, 'CNT-001');
        $reflection->getProperty('clientIdentifier')->setValue($counter, 'CLIENT-001');

        // Set snapshot as hydrate() would — only DB columns
        $snapshot = $reflection->getProperty('__snapshot');
        $snapshot->setValue($counter, [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
        ]);

        $result = $this->entityToArray->__invoke($counter);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('identifier', $result);
        $this->assertArrayHasKey('counter_number', $result);
        $this->assertArrayHasKey('client_identifier', $result);
        // Relations (empty or filled) must NOT appear — they're not in snapshot
        $this->assertArrayNotHasKey('counter_register', $result);
        $this->assertArrayNotHasKey('counter_gateway', $result);
    }

    public function testEntityWithoutRelationsUnchanged(): void
    {
        $entity = new class {
            private ?int $id = null;
            private ?string $name = null;
            private ?float $price = null;
            private array $__snapshot = ['id' => null, 'name' => null, 'price' => null];
        };

        $reflection = new ReflectionClass($entity);
        $reflection->getProperty('id')->setValue($entity, 1);
        $reflection->getProperty('name')->setValue($entity, 'Product');
        $reflection->getProperty('price')->setValue($entity, 19.99);

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals(['id' => 1, 'name' => 'Product', 'price' => 19.99], $result);
    }

    public function testFormatsBackedEnumAsValue(): void
    {
        $entity = new class {
            private GatewayType $type;
            private array $__snapshot = ['type' => 'ELECTRICITY'];

            public function __construct()
            {
                $this->type = GatewayType::Electricity;
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertSame('ELECTRICITY', $result['type']);
    }
}
