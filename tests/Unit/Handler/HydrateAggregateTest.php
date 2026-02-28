<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Address;
use JardisSupport\Data\Tests\Unit\Fixtures\Customer;
use PHPUnit\Framework\TestCase;

class HydrateAggregateTest extends TestCase
{
    private HydrateAggregate $hydrateAggregate;

    protected function setUp(): void
    {
        $this->hydrateAggregate = new HydrateAggregate(
            new ColumnNameToPropertyName(),
            new TypeCaster(),
            new SetSnapshot()
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
}
