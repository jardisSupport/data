<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\DiffEntities;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGateway;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for DiffEntities handler.
 */
class DiffEntitiesTest extends TestCase
{
    private DiffEntities $diffEntities;

    protected function setUp(): void
    {
        $this->diffEntities = new DiffEntities(
            new GetSnapshot(),
            new ColumnNameToPropertyName()
        );
    }

    public function testReturnsEmptyArrayWhenEntitiesAreIdentical(): void
    {
        $entity1 = $this->createEntity('John', 30);
        $entity2 = $this->createEntity('John', 30);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertEmpty($diff);
    }

    public function testDetectsSinglePropertyDifference(): void
    {
        $entity1 = $this->createEntity('John', 30);
        $entity2 = $this->createEntity('Jane', 30);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('name', $diff);
        $this->assertEquals('Jane', $diff['name']);
    }

    public function testDetectsMultiplePropertyDifferences(): void
    {
        $entity1 = $this->createEntityWithCity('John', 30, 'NYC');
        $entity2 = $this->createEntityWithCity('Jane', 25, 'NYC');

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(2, $diff);
        $this->assertArrayHasKey('name', $diff);
        $this->assertArrayHasKey('age', $diff);
        $this->assertEquals('Jane', $diff['name']);
        $this->assertEquals(25, $diff['age']);
    }

    public function testIgnoresSnapshotProperty(): void
    {
        $entity1 = $this->createEntityWithSnapshot('John', ['name' => 'Old John']);
        $entity2 = $this->createEntityWithSnapshot('John', ['name' => 'Different Snapshot']);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertEmpty($diff);
        $this->assertArrayNotHasKey('__snapshot', $diff);
    }

    public function testHandlesNullValues(): void
    {
        $entity1 = $this->createEntityNullable('John');
        $entity2 = $this->createEntityNullable(null);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('name', $diff);
        $this->assertNull($diff['name']);
    }

    public function testDetectsArrayDifferences(): void
    {
        $entity1 = $this->createEntityWithArray(['php', 'testing']);
        $entity2 = $this->createEntityWithArray(['php', 'mysql']);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('tags', $diff);
        $this->assertEquals(['php', 'mysql'], $diff['tags']);
    }

    public function testReturnsEmptyForIdenticalArrays(): void
    {
        $entity1 = $this->createEntityWithArray(['php', 'testing']);
        $entity2 = $this->createEntityWithArray(['php', 'testing']);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertEmpty($diff);
    }

    public function testHandlesDateTimeDifferences(): void
    {
        $entity1 = $this->createEntityWithDateTime(new DateTime('2024-01-15'));
        $entity2 = $this->createEntityWithDateTime(new DateTime('2024-01-20'));

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('created_at', $diff);
        $this->assertSame('2024-01-20 00:00:00', $diff['created_at']);
    }

    public function testThrowsExceptionForDifferentClasses(): void
    {
        $entity1 = new class {
            private string $name = 'John';
        };

        $entity2 = new class {
            private int $age = 30;
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot diff entities of different classes');

        $this->diffEntities->__invoke($entity1, $entity2);
    }

    public function testSkipsRelationPropertiesViaValueDetection(): void
    {
        $counter1 = new Counter();
        $counter2 = new Counter();
        $reflection = new ReflectionClass(Counter::class);

        // Set identical scalar values
        $reflection->getProperty('id')->setValue($counter1, 1);
        $reflection->getProperty('id')->setValue($counter2, 1);

        $reflection->getProperty('identifier')->setValue($counter1, 'abc-123');
        $reflection->getProperty('identifier')->setValue($counter2, 'abc-123');

        // Set snapshot with only scalar DB column keys (no relation keys)
        $snapshot = ['id' => 1, 'identifier' => 'abc-123'];
        $reflection->getProperty('__snapshot')->setValue($counter1, $snapshot);
        $reflection->getProperty('__snapshot')->setValue($counter2, $snapshot);

        // Set different relation data — should be ignored because relations are not in snapshot
        $gateway1 = new CounterGateway();
        (new ReflectionClass(CounterGateway::class))->getProperty('id')->setValue($gateway1, 1);

        $gateway2 = new CounterGateway();
        (new ReflectionClass(CounterGateway::class))->getProperty('id')->setValue($gateway2, 99);

        $reflection->getProperty('counterGateway')->setValue($counter1, $gateway1);
        $reflection->getProperty('counterGateway')->setValue($counter2, $gateway2);

        $diff = $this->diffEntities->__invoke($counter1, $counter2);

        $this->assertArrayNotHasKey('counter_gateway', $diff);
        $this->assertArrayNotHasKey('counter_register', $diff);
        $this->assertEmpty($diff);
    }

    public function testDetectsScalarDiffWhileSkippingRelations(): void
    {
        $counter1 = new Counter();
        $counter2 = new Counter();
        $reflection = new ReflectionClass(Counter::class);

        $reflection->getProperty('id')->setValue($counter1, 1);
        $reflection->getProperty('id')->setValue($counter2, 1);

        $reflection->getProperty('identifier')->setValue($counter1, 'abc-123');
        $reflection->getProperty('identifier')->setValue($counter2, 'xyz-789');

        // Set snapshot with only scalar DB column keys (no relation keys)
        $snapshot1 = ['id' => 1, 'identifier' => 'abc-123'];
        $snapshot2 = ['id' => 1, 'identifier' => 'xyz-789'];
        $reflection->getProperty('__snapshot')->setValue($counter1, $snapshot1);
        $reflection->getProperty('__snapshot')->setValue($counter2, $snapshot2);

        $diff = $this->diffEntities->__invoke($counter1, $counter2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('identifier', $diff);
        $this->assertEquals('xyz-789', $diff['identifier']);
        $this->assertArrayNotHasKey('counter_gateway', $diff);
        $this->assertArrayNotHasKey('counter_register', $diff);
    }

    public function testComparesDateTimeByValue(): void
    {
        $entity1 = $this->createEntityWithDateTime(new DateTime('2024-01-15 10:00:00'));
        $entity2 = $this->createEntityWithDateTime(new DateTime('2024-01-15 10:00:00'));

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertEmpty($diff);
    }

    public function testComparesDateTimeWithDateTimeImmutable(): void
    {
        $entity3 = $this->createEntityWithDateTimeInterface(
            new DateTime('2024-01-15 10:00:00')
        );
        $entity4 = $this->createEntityWithDateTimeInterface(
            new DateTimeImmutable('2024-01-15 10:00:00')
        );

        $diff = $this->diffEntities->__invoke($entity3, $entity4);

        $this->assertEmpty($diff);
    }

    public function testDetectsDifferencesBetweenDateTimeAndDateTimeImmutable(): void
    {
        $entity1 = $this->createEntityWithDateTimeInterface(
            new DateTime('2024-01-15 10:00:00')
        );
        $entity2 = $this->createEntityWithDateTimeInterface(
            new DateTimeImmutable('2024-01-20 10:00:00')
        );

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('created_at', $diff);
    }

    public function testCompareBackedEnumValues(): void
    {
        $entity1 = new class {
            private GatewayType $type;
            private array $__snapshot = ['type' => 'ELECTRICITY'];

            public function __construct()
            {
                $this->type = GatewayType::Electricity;
            }
        };

        $entity2 = clone $entity1;
        $reflection = new ReflectionClass($entity2);
        $reflection->getProperty('type')->setValue($entity2, GatewayType::Gas);
        $reflection->getProperty('__snapshot')->setValue($entity2, ['type' => 'GAS']);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('type', $diff);
        $this->assertSame('GAS', $diff['type']);
    }

    private function createEntity(string $name, int $age): object
    {
        return new class($name, $age) {
            private string $name;
            private int $age;
            private array $__snapshot;

            public function __construct(string $name, int $age)
            {
                $this->name = $name;
                $this->age = $age;
                $this->__snapshot = ['name' => $name, 'age' => $age];
            }
        };
    }

    private function createEntityWithCity(string $name, int $age, string $city): object
    {
        return new class($name, $age, $city) {
            private string $name;
            private int $age;
            private string $city;
            private array $__snapshot;

            public function __construct(string $name, int $age, string $city)
            {
                $this->name = $name;
                $this->age = $age;
                $this->city = $city;
                $this->__snapshot = ['name' => $name, 'age' => $age, 'city' => $city];
            }
        };
    }

    private function createEntityWithSnapshot(string $name, array $snapshot): object
    {
        return new class($name, $snapshot) {
            private string $name;
            private array $__snapshot;

            public function __construct(string $name, array $snapshot)
            {
                $this->name = $name;
                $this->__snapshot = $snapshot;
            }
        };
    }

    private function createEntityNullable(?string $name): object
    {
        return new class($name) {
            private ?string $name;
            private array $__snapshot;

            public function __construct(?string $name)
            {
                $this->name = $name;
                $this->__snapshot = ['name' => $name];
            }
        };
    }

    private function createEntityWithArray(array $tags): object
    {
        return new class($tags) {
            private array $tags;
            private array $__snapshot;

            public function __construct(array $tags)
            {
                $this->tags = $tags;
                $this->__snapshot = ['tags' => $tags];
            }
        };
    }

    private function createEntityWithDateTime(DateTime $createdAt): object
    {
        return new class($createdAt) {
            private DateTime $createdAt;
            private array $__snapshot;

            public function __construct(DateTime $createdAt)
            {
                $this->createdAt = $createdAt;
                $this->__snapshot = ['created_at' => $createdAt->format('Y-m-d H:i:s')];
            }
        };
    }

    private function createEntityWithDateTimeInterface(\DateTimeInterface $createdAt): object
    {
        return new class($createdAt) {
            /** @var \DateTimeInterface */
            private \DateTimeInterface $createdAt;
            private array $__snapshot;

            public function __construct(\DateTimeInterface $createdAt)
            {
                $this->createdAt = $createdAt;
                $this->__snapshot = ['created_at' => $createdAt->format('Y-m-d H:i:s')];
            }
        };
    }
}
