<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use DateTimeImmutable;
use JardisSupport\Data\Handler\EntityToArray;
use PHPUnit\Framework\TestCase;

class EntityToArrayTest extends TestCase
{
    private EntityToArray $entityToArray;

    protected function setUp(): void
    {
        $this->entityToArray = new EntityToArray();
    }

    public function testConvertsSimpleEntity(): void
    {
        $entity = new class {
            private string $name = 'John';
            private int $age = 30;
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

            public function __construct()
            {
                $this->createdAt = new DateTime('2024-01-15 14:30:45');
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-01-15 14:30:45', $result['createdAt']);
    }

    public function testHandlesNullValues(): void
    {
        $entity = new class {
            private ?string $name = null;
            private ?int $age = null;
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
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals(['php', 'testing'], $result['tags']);
        $this->assertEquals(['key' => 'value'], $result['metadata']);
    }

    public function testConvertsNestedObjectToArray(): void
    {
        $nested = new class {
            private string $city = 'Berlin';
            private string $country = 'Germany';
        };

        $entity = new class($nested) {
            private object $address;

            public function __construct(object $address)
            {
                $this->address = $address;
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertIsArray($result['address']);
        $this->assertEquals('Berlin', $result['address']['city']);
        $this->assertEquals('Germany', $result['address']['country']);
    }

    public function testConvertsArrayOfObjectsToArrayOfArrays(): void
    {
        $item1 = new class {
            private string $name = 'Item 1';
        };

        $item2 = new class {
            private string $name = 'Item 2';
        };

        $entity = new class($item1, $item2) {
            private array $items;

            public function __construct(object $item1, object $item2)
            {
                $this->items = [$item1, $item2];
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertIsArray($result['items']);
        $this->assertCount(2, $result['items']);
        $this->assertEquals('Item 1', $result['items'][0]['name']);
        $this->assertEquals('Item 2', $result['items'][1]['name']);
    }

    public function testSkipsUninitializedProperties(): void
    {
        $entity = new class {
            private string $initialized = 'value';
            private string $uninitialized;
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertArrayHasKey('initialized', $result);
        $this->assertArrayNotHasKey('uninitialized', $result);
    }

    public function testHandlesDateTimeInArray(): void
    {
        $entity = new class {
            private array $dates;

            public function __construct()
            {
                $this->dates = [
                    new DateTime('2024-01-15 10:00:00'),
                    new DateTime('2024-01-16 11:00:00'),
                ];
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-01-15 10:00:00', $result['dates'][0]);
        $this->assertEquals('2024-01-16 11:00:00', $result['dates'][1]);
    }

    public function testFormatsDateTimeImmutableToString(): void
    {
        $entity = new class {
            private DateTimeImmutable $createdAt;

            public function __construct()
            {
                $this->createdAt = new DateTimeImmutable('2024-06-20 09:15:30');
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-06-20 09:15:30', $result['createdAt']);
    }

    public function testFormatsDateTimeImmutableInArray(): void
    {
        $entity = new class {
            private array $dates;

            public function __construct()
            {
                $this->dates = [
                    new DateTimeImmutable('2024-01-15 10:00:00'),
                    new DateTime('2024-01-16 11:00:00'),
                ];
            }
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertEquals('2024-01-15 10:00:00', $result['dates'][0]);
        $this->assertEquals('2024-01-16 11:00:00', $result['dates'][1]);
    }

    public function testIncludesPublicProperties(): void
    {
        $entity = new class {
            public string $publicName = 'Public';
            private string $privateName = 'Private';
        };

        $result = $this->entityToArray->__invoke($entity);

        $this->assertArrayHasKey('publicName', $result);
        $this->assertEquals('Public', $result['publicName']);
        $this->assertArrayHasKey('privateName', $result);
        $this->assertEquals('Private', $result['privateName']);
    }

    public function testCachesClassProperties(): void
    {
        $entity1 = new class {
            private string $name = 'First';
        };

        // Same class, same instance — second call uses cached properties
        $result1 = $this->entityToArray->__invoke($entity1);
        $result2 = $this->entityToArray->__invoke($entity1);

        $this->assertEquals('First', $result1['name']);
        $this->assertEquals('First', $result2['name']);
    }
}
