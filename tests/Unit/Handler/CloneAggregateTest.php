<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\CloneAggregate;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGateway;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGatewayRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\Gateway;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CloneAggregate handler (deep clone, full object graph).
 */
class CloneAggregateTest extends TestCase
{
    private CloneAggregate $cloneAggregate;
    private HydrateAggregate $hydrateAggregate;

    protected function setUp(): void
    {
        $getSnapshot = new GetSnapshot();
        $setSnapshot = new SetSnapshot();
        $columnNameToPropertyName = new ColumnNameToPropertyName();

        $this->cloneAggregate = new CloneAggregate($getSnapshot, $setSnapshot);
        $this->hydrateAggregate = new HydrateAggregate(
            $columnNameToPropertyName,
            new TypeCaster(),
            $setSnapshot,
            $getSnapshot,
            new GetPropertyValue($columnNameToPropertyName)
        );
    }

    public function testDeepClonesNestedObject(): void
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

        $clone = $this->cloneAggregate->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertNotSame($entity->getNested(), $clone->getNested());
        $this->assertEquals($entity->getNested()->value, $clone->getNested()->value);
    }

    public function testDeepClonesArrayOfObjects(): void
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

        $clone = $this->cloneAggregate->__invoke($entity);

        $this->assertNotSame($entity, $clone);
        $this->assertCount(2, $clone->getItems());
        $this->assertNotSame($entity->getItems()[0], $clone->getItems()[0]);
        $this->assertNotSame($entity->getItems()[1], $clone->getItems()[1]);
        $this->assertEquals($entity->getItems()[0]->value, $clone->getItems()[0]->value);
        $this->assertEquals($entity->getItems()[1]->value, $clone->getItems()[1]->value);
    }

    public function testDeepClonesThreeLevelNesting(): void
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

        $clone = $this->cloneAggregate->__invoke($entity);

        $this->assertNotSame($entity->getMiddle(), $clone->getMiddle());
        $this->assertNotSame(
            $entity->getMiddle()->getInner(),
            $clone->getMiddle()->getInner()
        );
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

        $clone = $this->cloneAggregate->__invoke($entity);

        $this->assertEquals(['address' => 'ref'], $clone->getSnapshot());
        $this->assertEquals(['city' => 'Berlin'], $clone->getAddress()->getSnapshot());
    }

    public function testDeepClonesCounterAggregate(): void
    {
        $counter = new Counter();

        $this->hydrateAggregate->__invoke($counter, [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
                'gateway' => [
                    'id' => 100,
                    'identifier' => 'GW-001',
                    'type' => 'ELECTRICITY',
                ],
                'counter_gateway_register' => [
                    ['id' => 50, 'counter_gateway_id' => 3, 'register_id' => 5,
                        'configuration_identification' => 'CFG-001'],
                ],
            ],
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5, 'related_type' => 'POWER',
                    'active_from' => '2024-01-01 00:00:00'],
            ],
        ]);

        /** @var Counter $clone */
        $clone = $this->cloneAggregate->__invoke($counter);

        // Level 1: Counter
        $this->assertNotSame($counter, $clone);
        $this->assertSame(1, $clone->getId());
        $this->assertSame('abc-123', $clone->getIdentifier());

        // Level 2: CounterGateway
        $this->assertNotSame($counter->getCounterGateway(), $clone->getCounterGateway());
        $this->assertSame(3, $clone->getCounterGateway()->getId());

        // Level 3: Gateway
        $this->assertNotSame(
            $counter->getCounterGateway()->getGateway(),
            $clone->getCounterGateway()->getGateway()
        );
        $this->assertSame(100, $clone->getCounterGateway()->getGateway()->getId());

        // Level 2: CounterRegister[]
        $this->assertCount(1, $clone->getCounterRegister());
        $this->assertNotSame(
            $counter->getCounterRegister()[0],
            $clone->getCounterRegister()[0]
        );
        $this->assertSame(10, $clone->getCounterRegister()[0]->getId());

        // Snapshots preserved per level
        $this->assertNotEmpty($clone->getSnapshot());
        $this->assertNotEmpty($clone->getCounterGateway()->getSnapshot());
        $this->assertNotEmpty($clone->getCounterGateway()->getGateway()->getSnapshot());
        $this->assertNotEmpty($clone->getCounterRegister()[0]->getSnapshot());
    }

    public function testDeepCloneClonesDateTime(): void
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

        $clone = $this->cloneAggregate->__invoke($entity);

        $this->assertNotSame($entity->getCreatedAt(), $clone->getCreatedAt());
        $this->assertEquals(
            $entity->getCreatedAt()->format('Y-m-d H:i:s'),
            $clone->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }
}
