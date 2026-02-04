<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use InvalidArgumentException;
use JardisSupport\Data\Handler\DiffEntities;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DiffEntitiesTest extends TestCase
{
    private DiffEntities $diffEntities;

    protected function setUp(): void
    {
        $this->diffEntities = new DiffEntities();
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

    public function testSkipsArrayComparison(): void
    {
        $entity1 = $this->createEntityWithArray(['php', 'testing']);
        $entity2 = $this->createEntityWithArray(['php', 'mysql']);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        // Arrays are skipped (always considered equal)
        $this->assertEmpty($diff);
        $this->assertArrayNotHasKey('tags', $diff);
    }

    public function testHandlesDateTimeDifferences(): void
    {
        $entity1 = $this->createEntityWithDateTime(new DateTime('2024-01-15'));
        $entity2 = $this->createEntityWithDateTime(new DateTime('2024-01-20'));

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        $this->assertCount(1, $diff);
        $this->assertArrayHasKey('createdAt', $diff);
        $this->assertInstanceOf(DateTime::class, $diff['createdAt']);
        $this->assertEquals('2024-01-20', $diff['createdAt']->format('Y-m-d'));
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

    public function testSkipsNestedObjectComparison(): void
    {
        $nested1 = (object) ['value' => 'first'];
        $nested2 = (object) ['value' => 'second'];

        $entity1 = $this->createEntityWithObject($nested1);
        $entity2 = $this->createEntityWithObject($nested2);

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        // Objects are skipped (always considered equal)
        $this->assertEmpty($diff);
        $this->assertArrayNotHasKey('nested', $diff);
    }

    public function testComparesDateTimeByValue(): void
    {
        // Same timestamp, different instances
        $entity1 = $this->createEntityWithDateTime(new DateTime('2024-01-15 10:00:00'));
        $entity2 = $this->createEntityWithDateTime(new DateTime('2024-01-15 10:00:00'));

        $diff = $this->diffEntities->__invoke($entity1, $entity2);

        // DateTime with same timestamp should be considered equal
        $this->assertEmpty($diff);
    }

    private function createEntity(string $name, int $age): object
    {
        $class = new class('', 0) {
            private string $name;
            private int $age;

            public function __construct(string $name, int $age)
            {
                $this->name = $name;
                $this->age = $age;
            }
        };

        $reflection = new ReflectionClass($class);
        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setAccessible(true);
        $nameProperty->setValue($class, $name);

        $ageProperty = $reflection->getProperty('age');
        $ageProperty->setAccessible(true);
        $ageProperty->setValue($class, $age);

        return $class;
    }

    private function createEntityWithCity(string $name, int $age, string $city): object
    {
        return new class($name, $age, $city) {
            private string $name;
            private int $age;
            private string $city;

            public function __construct(string $name, int $age, string $city)
            {
                $this->name = $name;
                $this->age = $age;
                $this->city = $city;
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

            public function __construct(?string $name)
            {
                $this->name = $name;
            }
        };
    }

    private function createEntityWithArray(array $tags): object
    {
        return new class($tags) {
            private array $tags;

            public function __construct(array $tags)
            {
                $this->tags = $tags;
            }
        };
    }

    private function createEntityWithDateTime(DateTime $createdAt): object
    {
        return new class($createdAt) {
            private DateTime $createdAt;

            public function __construct(DateTime $createdAt)
            {
                $this->createdAt = $createdAt;
            }
        };
    }

    private function createEntityWithObject(object $nested): object
    {
        return new class($nested) {
            private object $nested;

            public function __construct(object $nested)
            {
                $this->nested = $nested;
            }
        };
    }
}
