<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Address;
use JardisSupport\Data\Tests\Unit\Fixtures\Country;
use JardisSupport\Data\Tests\Unit\Fixtures\Customer;
use JardisSupport\Data\Tests\Unit\Fixtures\OrderItem;
use PHPUnit\Framework\TestCase;

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

        // Should not throw exception
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

        // Simulate PDO returning strings
        $data = ['count' => '42', 'price' => '9.99', 'active' => '1'];

        $this->hydrateAggregate->__invoke($aggregate, $data);

        $this->assertSame(42, $aggregate->getCount());
        $this->assertSame(9.99, $aggregate->getPrice());
        $this->assertTrue($aggregate->isActive());

        // Snapshot should contain typed values, not raw strings
        $snapshot = $aggregate->getSnapshot();
        $this->assertSame(42, $snapshot['count']);
        $this->assertSame(9.99, $snapshot['price']);
        $this->assertTrue($snapshot['active']);
    }

    public function testSnapshotKeepsRawDataForArraysAndObjects(): void
    {
        $customer = new Customer();

        $addressData = [
            'street' => '123 Main St',
            'city' => 'Berlin',
            'zip_code' => '10115',
        ];

        $data = [
            'id' => 'cust-snap',
            'name' => 'Snapshot Test',
            'email' => 'snap@example.com',
            'billing_address' => $addressData,
            'tags' => ['vip', 'premium'],
        ];

        $this->hydrateAggregate->__invoke($customer, $data);

        $snapshot = $customer->getSnapshot();

        // Scalar values are typed in snapshot
        $this->assertSame('cust-snap', $snapshot['id']);
        $this->assertSame('Snapshot Test', $snapshot['name']);

        // Arrays/objects keep raw data in snapshot
        $this->assertSame($addressData, $snapshot['billing_address']);
        $this->assertSame(['vip', 'premium'], $snapshot['tags']);
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
}
