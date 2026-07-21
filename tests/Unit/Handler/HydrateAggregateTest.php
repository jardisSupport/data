<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Address;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGateway;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGatewayRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\Country;
use JardisSupport\Data\Tests\Unit\Fixtures\Customer;
use JardisSupport\Data\Tests\Unit\Fixtures\Gateway;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use JardisSupport\Data\Tests\Unit\Fixtures\OrderItem;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HydrateAggregate handler.
 */
class HydrateAggregateTest extends TestCase
{
    private HydrateAggregate $hydrateAggregate;

    protected function setUp(): void
    {
        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $this->hydrateAggregate = new HydrateAggregate(
            $columnNameToPropertyName,
            new TypeCaster(),
            new SetSnapshot(),
            new GetSnapshot(),
            new GetPropertyValue($columnNameToPropertyName)
        );
    }

    public function testHydratesSimpleProperties(): void
    {
        $aggregate = new class {
            private ?string $name = null;
            private ?int $count = null;
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getCount(): ?int
            {
                return $this->count;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = ['name' => 'Test', 'count' => 42];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertEquals('Test', $aggregate->getName());
        $this->assertEquals(42, $aggregate->getCount());
    }

    public function testSetsSnapshotAfterHydration(): void
    {
        $aggregate = new class {
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

        $data = ['name' => 'Test'];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertEquals($data, $aggregate->getSnapshot());
    }

    public function testHandlesSnakeCaseColumns(): void
    {
        $aggregate = new class {
            private ?string $firstName = null;
            private ?string $lastName = null;
            private array $__snapshot = [];

            public function getFirstName(): ?string
            {
                return $this->firstName;
            }

            public function getLastName(): ?string
            {
                return $this->lastName;
            }
        };

        $data = ['first_name' => 'John', 'last_name' => 'Doe'];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertEquals('John', $aggregate->getFirstName());
        $this->assertEquals('Doe', $aggregate->getLastName());
    }

    public function testHydratesNestedObject(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'billing_address' => [
                'street' => '123 Main St',
                'city' => 'Berlin',
                'zip_code' => '10115',
            ],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $this->assertEquals('cust-123', $customer->getId());
        $this->assertEquals('John Doe', $customer->getName());
        $this->assertInstanceOf(Address::class, $customer->getBillingAddress());
        $this->assertEquals('123 Main St', $customer->getBillingAddress()->getStreet());
        $this->assertEquals('Berlin', $customer->getBillingAddress()->getCity());
    }

    public function testUsesSetterWhenAvailable(): void
    {
        $aggregate = new class {
            private string $name;
            public bool $setterCalled = false;
            private array $__snapshot = [];

            public function setName(string $name): void
            {
                $this->setterCalled = true;
                $this->name = strtoupper($name);
            }

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->hydrateAggregate->__invoke($aggregate, ['name' => 'test']);

        $this->assertTrue($aggregate->setterCalled);
        $this->assertEquals('TEST', $aggregate->getName());
    }

    public function testIgnoresUnknownProperties(): void
    {
        $aggregate = new class {
            private ?string $name = null;
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $this->hydrateAggregate->__invoke($aggregate, [
            'name' => 'Test',
            'unknown' => 'value',
        ]);

        $this->assertEquals('Test', $aggregate->getName());
    }

    public function testHandlesNullValues(): void
    {
        $aggregate = new class {
            private ?string $name = 'initial';
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $this->hydrateAggregate->__invoke($aggregate, ['name' => null]);

        $this->assertNull($aggregate->getName());
    }

    public function testHydratesScalarArrays(): void
    {
        $aggregate = new class {
            private array $tags = [];
            private array $__snapshot = [];

            public function getTags(): array
            {
                return $this->tags;
            }
        };

        $data = ['tags' => ['php', 'testing', 'data']];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertEquals(['php', 'testing', 'data'], $aggregate->getTags());
    }

    public function testHydratesArrayOfTypedObjects(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-456',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'recent_orders' => [
                ['product_name' => 'Widget', 'quantity' => 2, 'price' => 19.99],
                ['product_name' => 'Gadget', 'quantity' => 1, 'price' => 49.99],
            ],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $orders = $customer->getRecentOrders();
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(OrderItem::class, $orders[0]);
        $this->assertEquals('Widget', $orders[0]->getProductName());
        $this->assertSame(2, $orders[0]->getQuantity());
        $this->assertSame(19.99, $orders[0]->getPrice());
        $this->assertInstanceOf(OrderItem::class, $orders[1]);
        $this->assertEquals('Gadget', $orders[1]->getProductName());
    }

    public function testHydratesDeeplyNestedObjects(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-789',
            'name' => 'Deep Nesting',
            'email' => 'deep@example.com',
            'billing_address' => [
                'street' => 'Nested St',
                'city' => 'Munich',
                'zip_code' => '80331',
                'country' => [
                    'code' => 'DE',
                    'name' => 'Germany',
                ],
            ],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $address = $customer->getBillingAddress();
        $this->assertInstanceOf(Address::class, $address);
        $this->assertInstanceOf(Country::class, $address->getCountry());
        $this->assertEquals('DE', $address->getCountry()->getCode());
        $this->assertEquals('Germany', $address->getCountry()->getName());
    }

    public function testPassesAlreadyHydratedObjectThrough(): void
    {
        $aggregate = new class {
            private ?Address $address = null;
            private array $__snapshot = [];

            public function getAddress(): ?Address
            {
                return $this->address;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $address = new Address();
        $this->hydrateAggregate->__invoke($address, [
            'street' => 'Pre-Built St',
            'city' => 'Hamburg',
            'zip_code' => '20095',
        ]);

        $this->hydrateAggregate->__invoke($aggregate, ['address' => $address]);

        $this->assertSame($address, $aggregate->getAddress());
        $this->assertEquals('Pre-Built St', $aggregate->getAddress()->getStreet());
    }

    public function testHydratesAssociativeArrayWithoutTypeHint(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-meta',
            'name' => 'Meta User',
            'email' => 'meta@example.com',
            'metadata' => ['role' => 'admin', 'level' => 5],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $this->assertEquals(['role' => 'admin', 'level' => 5], $customer->getMetadata());
    }

    public function testHandlesEmptyArray(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-empty',
            'name' => 'Empty Orders',
            'email' => 'empty@example.com',
            'recent_orders' => [],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $this->assertEmpty($customer->getRecentOrders());
    }

    public function testTypeCastsScalarValues(): void
    {
        $aggregate = new class {
            private int $count = 0;
            private float $price = 0.0;
            private bool $active = false;
            private array $__snapshot = [];

            public function getCount(): int
            {
                return $this->count;
            }

            public function getPrice(): float
            {
                return $this->price;
            }

            public function isActive(): bool
            {
                return $this->active;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = ['count' => '42', 'price' => '9.99', 'active' => '1'];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertSame(42, $aggregate->getCount());
        $this->assertSame(9.99, $aggregate->getPrice());
        $this->assertTrue($aggregate->isActive());

        $snapshot = $aggregate->getSnapshot();
        $this->assertSame(42, $snapshot['count']);
        $this->assertSame(9.99, $snapshot['price']);
        $this->assertTrue($snapshot['active']);
    }

    public function testSnapshotOnlyContainsDbColumnValues(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
                ['id' => 20, 'counter_id' => 1, 'register_id' => 8],
            ],
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
                'gateway' => [
                    'id' => 100,
                    'identifier' => 'GW-001',
                    'type' => 'ELECTRICITY',
                ],
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $snapshot = $counter->getSnapshot();

        // Scalar DB-column values should be in snapshot
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertArrayHasKey('identifier', $snapshot);
        $this->assertArrayHasKey('counter_number', $snapshot);
        $this->assertArrayHasKey('client_identifier', $snapshot);
        $this->assertSame(1, $snapshot['id']);
        $this->assertSame('abc-123', $snapshot['identifier']);

        // Non-scalar values must NOT be in snapshot
        $this->assertArrayNotHasKey('counter_register', $snapshot);
        $this->assertArrayNotHasKey('counter_gateway', $snapshot);
    }

    public function testRelationPropertiesAreStillHydratedCorrectly(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
            ],
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $this->assertCount(1, $counter->getCounterRegister());
        $this->assertInstanceOf(CounterRegister::class, $counter->getCounterRegister()[0]);
        $this->assertSame(10, $counter->getCounterRegister()[0]->getId());

        $this->assertNotNull($counter->getCounterGateway());
        $this->assertInstanceOf(CounterGateway::class, $counter->getCounterGateway());
        $this->assertSame(3, $counter->getCounterGateway()->getId());
    }

    public function testHydratesOneRelationViaTypedSetter(): void
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
                'active_until' => null,
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $gateway = $counter->getCounterGateway();
        $this->assertInstanceOf(CounterGateway::class, $gateway);
        $this->assertSame(3, $gateway->getId());
        $this->assertSame(1, $gateway->getCounterId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $gateway->getActiveFrom());
        $this->assertNull($gateway->getActiveUntil());
    }

    public function testHydratesThreeLevelNesting(): void
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
                'gateway' => [
                    'id' => 100,
                    'identifier' => 'GW-001',
                    'type' => 'ELECTRICITY',
                ],
                'counter_gateway_register' => [
                    [
                        'id' => 50,
                        'counter_gateway_id' => 3,
                        'register_id' => 5,
                        'configuration_identification' => 'CFG-001',
                    ],
                    [
                        'id' => 51,
                        'counter_gateway_id' => 3,
                        'register_id' => 6,
                        'configuration_identification' => 'CFG-002',
                    ],
                ],
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        // Level 1: Counter
        $this->assertSame(1, $counter->getId());

        // Level 2: CounterGateway (via typed setter)
        $counterGateway = $counter->getCounterGateway();
        $this->assertInstanceOf(CounterGateway::class, $counterGateway);
        $this->assertSame(3, $counterGateway->getId());

        // Level 3a: Gateway (via typed setter on CounterGateway)
        $gateway = $counterGateway->getGateway();
        $this->assertInstanceOf(Gateway::class, $gateway);
        $this->assertSame(100, $gateway->getId());
        $this->assertSame('GW-001', $gateway->getIdentifier());
        $this->assertSame(GatewayType::Electricity, $gateway->getType());

        // Level 3b: CounterGatewayRegister[] (many relation)
        $registers = $counterGateway->getCounterGatewayRegister();
        $this->assertCount(2, $registers);
        $this->assertInstanceOf(CounterGatewayRegister::class, $registers[0]);
        $this->assertSame(50, $registers[0]->getId());
        $this->assertSame('CFG-001', $registers[0]->getConfigurationIdentification());
        $this->assertInstanceOf(CounterGatewayRegister::class, $registers[1]);
        $this->assertSame(51, $registers[1]->getId());
    }

    public function testChildEntitySnapshotsOnlyContainDbColumns(): void
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
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        // CounterGateway snapshot should only have scalar DB columns
        $cgSnapshot = $counter->getCounterGateway()->getSnapshot();
        $this->assertArrayHasKey('id', $cgSnapshot);
        $this->assertArrayHasKey('counter_id', $cgSnapshot);
        $this->assertArrayHasKey('active_from', $cgSnapshot);
        $this->assertArrayNotHasKey('gateway', $cgSnapshot);
        $this->assertArrayNotHasKey('counter_gateway_register', $cgSnapshot);

        // Gateway (leaf) snapshot should have its scalar values
        $gwSnapshot = $counter->getCounterGateway()->getGateway()->getSnapshot();
        $this->assertArrayHasKey('id', $gwSnapshot);
        $this->assertArrayHasKey('identifier', $gwSnapshot);
        $this->assertArrayHasKey('type', $gwSnapshot);
    }

    public function testNoFalsePositiveChangesAfterAggregateHydration(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
            ],
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
                'gateway' => [
                    'id' => 100,
                    'identifier' => 'GW-001',
                    'type' => 'ELECTRICITY',
                ],
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $snapshot = $counter->getSnapshot();

        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $getPropertyValue = new GetPropertyValue($columnNameToPropertyName);

        foreach ($snapshot as $key => $originalValue) {
            $currentValue = $getPropertyValue($counter, $key);
            $this->assertSame(
                $originalValue,
                $currentValue,
                "False positive change detected for key '$key'"
            );
        }
    }

    public function testHandlesNumericKeysInTopLevelData(): void
    {
        $aggregate = new class {
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

        $data = [
            'name' => 'Test',
            0 => ['id' => 1, 'value' => 'ignored'],
            1 => ['id' => 2, 'value' => 'also ignored'],
        ];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertEquals('Test', $aggregate->getName());

        $snapshot = $aggregate->getSnapshot();
        $this->assertArrayHasKey('name', $snapshot);
        $this->assertArrayNotHasKey(0, $snapshot);
        $this->assertArrayNotHasKey(1, $snapshot);
    }

    public function testHandlesAggregateWithNumericCollectionKeys(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_register' => [
                0 => ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
                1 => ['id' => 20, 'counter_id' => 1, 'register_id' => 8],
            ],
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $this->assertSame(1, $counter->getId());
        $this->assertSame('abc-123', $counter->getIdentifier());
        $this->assertCount(2, $counter->getCounterRegister());
        $this->assertSame(10, $counter->getCounterRegister()[0]->getId());
        $this->assertSame(20, $counter->getCounterRegister()[1]->getId());
        $this->assertNotNull($counter->getCounterGateway());
        $this->assertSame(3, $counter->getCounterGateway()->getId());
    }

    public function testNumericKeysSkippedInNestedObjectHydration(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-num',
            'name' => 'Numeric Test',
            'email' => 'num@example.com',
            0 => 'should-be-ignored',
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $this->assertEquals('cust-num', $customer->getId());
        $this->assertEquals('Numeric Test', $customer->getName());
    }

    public function testManyRelationWithTypedChildren(): void
    {
        $counter = new Counter();

        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_register' => [
                [
                    'id' => 10,
                    'counter_id' => 1,
                    'register_id' => 5,
                    'related_type' => 'POWER',
                    'active_from' => '2024-01-01 00:00:00',
                    'active_until' => null,
                ],
                [
                    'id' => 20,
                    'counter_id' => 1,
                    'register_id' => 8,
                    'related_type' => 'GAS',
                    'active_from' => '2024-06-01 00:00:00',
                    'active_until' => '2025-12-31 23:59:59',
                ],
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $registers = $counter->getCounterRegister();
        $this->assertCount(2, $registers);

        $this->assertInstanceOf(CounterRegister::class, $registers[0]);
        $this->assertSame(10, $registers[0]->getId());
        $this->assertSame(1, $registers[0]->getCounterId());
        $this->assertSame(5, $registers[0]->getRegisterId());
        $this->assertSame('POWER', $registers[0]->getRelatedType());

        $this->assertInstanceOf(CounterRegister::class, $registers[1]);
        $this->assertSame(20, $registers[1]->getId());
        $this->assertSame('GAS', $registers[1]->getRelatedType());
    }

    public function testHydratesArrayWithGenericDocblockSyntax(): void
    {
        $aggregate = new class {
            /**
             * @var array<int, \JardisSupport\Data\Tests\Unit\Fixtures\OrderItem>
             */
            private array $items = [];
            private array $__snapshot = [];

            /**
             * @return OrderItem[]
             */
            public function getItems(): array
            {
                return $this->items;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'items' => [
                ['product_name' => 'Alpha', 'quantity' => 1, 'price' => 10.0],
                ['product_name' => 'Beta', 'quantity' => 3, 'price' => 25.0],
            ],
        ];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $items = $aggregate->getItems();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(OrderItem::class, $items[0]);
        $this->assertEquals('Alpha', $items[0]->getProductName());
        $this->assertInstanceOf(OrderItem::class, $items[1]);
        $this->assertEquals('Beta', $items[1]->getProductName());
    }

    public function testHydratesAssociativeArrayOfObjects(): void
    {
        $aggregate = new class {
            /**
             * @var \JardisSupport\Data\Tests\Unit\Fixtures\Address[]
             */
            private array $addresses = [];
            private array $__snapshot = [];

            public function getAddresses(): array
            {
                return $this->addresses;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = [
            'addresses' => [
                'home' => ['street' => 'Home St', 'city' => 'Berlin', 'zip_code' => '10115'],
                'work' => ['street' => 'Work Ave', 'city' => 'Munich', 'zip_code' => '80331'],
            ],
        ];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $addresses = $aggregate->getAddresses();
        $this->assertCount(2, $addresses);
        $this->assertArrayHasKey('home', $addresses);
        $this->assertArrayHasKey('work', $addresses);
        $this->assertInstanceOf(Address::class, $addresses['home']);
        $this->assertInstanceOf(Address::class, $addresses['work']);
        $this->assertEquals('Berlin', $addresses['home']->getCity());
        $this->assertEquals('Munich', $addresses['work']->getCity());
    }

    public function testHydratesMultipleNestedObjects(): void
    {
        $customer = new Customer();

        $data = [
            'id' => 'cust-multi',
            'name' => 'Multi Address',
            'email' => 'multi@example.com',
            'billing_address' => [
                'street' => 'Billing St',
                'city' => 'Berlin',
                'zip_code' => '10115',
            ],
            'shipping_address' => [
                'street' => 'Shipping Ave',
                'city' => 'Munich',
                'zip_code' => '80331',
            ],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $this->assertInstanceOf(Address::class, $customer->getBillingAddress());
        $this->assertInstanceOf(Address::class, $customer->getShippingAddress());
        $this->assertEquals('Berlin', $customer->getBillingAddress()->getCity());
        $this->assertEquals('Munich', $customer->getShippingAddress()->getCity());
    }

    public function testHydratesBackedEnumViaTypeCaster(): void
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
                'gateway' => [
                    'id' => 100,
                    'identifier' => 'GW-001',
                    'type' => 'ELECTRICITY',
                ],
            ],
        ];

        $this->hydrateAggregate->__invoke($counter, $data);

        $gateway = $counter->getCounterGateway()->getGateway();
        $this->assertSame(GatewayType::Electricity, $gateway->getType());
    }
}
