<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit;

use JardisSupport\Data\Hydration;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for Hydration.
 */
class HydrationTest extends TestCase
{
    private Hydration $hydration;

    protected function setUp(): void
    {
        $this->hydration = new Hydration();
    }

    public function testFullEntityLifecycleWithRealData(): void
    {
        $user = new class {
            private ?int $id = null;
            private ?string $name = null;
            private ?string $email = null;
            private ?int $age = null;
            private array $__snapshot = [];

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(int $id): void
            {
                $this->id = $id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function setName(string $name): void
            {
                $this->name = $name;
            }

            public function getEmail(): ?string
            {
                return $this->email;
            }

            public function setEmail(string $email): void
            {
                $this->email = $email;
            }

            public function getAge(): ?int
            {
                return $this->age;
            }

            public function setAge(int $age): void
            {
                $this->age = $age;
            }
        };

        $dbRow = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $this->hydration->hydrate($user, $dbRow);

        $this->assertSame(1, $user->getId());
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertSame(30, $user->getAge());

        $snapshot = $this->hydration->getSnapshot($user);
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertSame(1, $snapshot['id']);

        $this->assertEmpty($this->hydration->getChanges($user));

        $user->setName('Jane Doe');
        $user->setAge(31);

        $changes = $this->hydration->getChanges($user);
        $this->assertNotEmpty($changes);
        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('age', $changes);
        $this->assertSame('Jane Doe', $changes['name']);
        $this->assertSame(31, $changes['age']);
        $this->assertArrayNotHasKey('email', $changes);

        $changedFields = $this->hydration->getChangedFields($user);
        $this->assertContains('name', $changedFields);
        $this->assertContains('age', $changedFields);
        $this->assertNotContains('email', $changedFields);

        $array = $this->hydration->toArray($user);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Jane Doe', $array['name']);
        $this->assertSame(31, $array['age']);

        $clone = $this->hydration->clone($user);
        $this->assertNotSame($user, $clone);
        $this->assertSame($user->getName(), $clone->getName());

        $clone->setName('Bob');
        $this->assertNotSame($user->getName(), $clone->getName());

        $diff = $this->hydration->diff($user, $clone);
        $this->assertArrayHasKey('name', $diff);
        $this->assertSame('Bob', $diff['name']);
    }

    public function testLoadMultipleEntitiesFromDatabaseRows(): void
    {
        $template = new class {
            private ?int $id = null;
            private ?string $name = null;

            public function setId(int $id): void
            {
                $this->id = $id;
            }

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setName(string $name): void
            {
                $this->name = $name;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $rows = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
            ['id' => 3, 'name' => 'User 3'],
        ];

        $entities = $this->hydration->loadMultiple($template, $rows);

        $this->assertCount(3, $entities);
        $this->assertSame(1, $entities[0]->getId());
        $this->assertSame('User 1', $entities[0]->getName());
        $this->assertSame(2, $entities[1]->getId());
        $this->assertSame('User 2', $entities[1]->getName());

        $entities[0]->setName('Modified');
        $this->assertNotSame($entities[0]->getName(), $entities[1]->getName());
    }

    public function testHydrateAggregateWithSimpleData(): void
    {
        $entity = new class {
            private ?string $name = null;
            private ?string $description = null;

            public function setName(string $name): void
            {
                $this->name = $name;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function setDescription(string $description): void
            {
                $this->description = $description;
            }

            public function getDescription(): ?string
            {
                return $this->description;
            }
        };

        $data = [
            'name' => 'Test Entity',
            'description' => 'Test Description',
        ];

        $this->hydration->hydrateAggregate($entity, $data);

        $this->assertSame('Test Entity', $entity->getName());
        $this->assertSame('Test Description', $entity->getDescription());
    }

    public function testChangeDetectionOnNestedObjectAfterAggregateHydration(): void
    {
        $aggregate = new class {
            private ?string $name = null;
            private array $__snapshot = [];

            public function setName(string $name): void
            {
                $this->name = $name;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $data = ['name' => 'Original'];
        $this->hydration->hydrateAggregate($aggregate, $data);

        $this->assertEmpty($this->hydration->getChanges($aggregate));

        $aggregate->setName('Modified');
        $this->assertNotEmpty($this->hydration->getChanges($aggregate));
        $changes = $this->hydration->getChanges($aggregate);
        $this->assertSame('Modified', $changes['name']);
    }

    public function testNoFalsePositivesAfterHydrationWithStringValues(): void
    {
        $entity = new class {
            private ?int $count = null;
            private ?float $price = null;
            private ?bool $active = null;
            private array $__snapshot = [];

            public function setCount(int $count): void
            {
                $this->count = $count;
            }

            public function getCount(): ?int
            {
                return $this->count;
            }

            public function setPrice(float $price): void
            {
                $this->price = $price;
            }

            public function getPrice(): ?float
            {
                return $this->price;
            }

            public function setActive(bool $active): void
            {
                $this->active = $active;
            }

            public function isActive(): ?bool
            {
                return $this->active;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $dbRow = [
            'count' => '42',
            'price' => '99.99',
            'active' => '1',
        ];

        $this->hydration->hydrate($entity, $dbRow);

        $this->assertEmpty($this->hydration->getChanges($entity));

        $snapshot = $this->hydration->getSnapshot($entity);
        $this->assertSame(42, $snapshot['count']);
        $this->assertSame(99.99, $snapshot['price']);
        $this->assertTrue($snapshot['active']);
    }

    public function testTypeConversionDuringHydration(): void
    {
        $entity = new class {
            private ?int $count = null;
            private ?float $price = null;
            private ?bool $active = null;

            public function setCount(int $count): void
            {
                $this->count = $count;
            }

            public function getCount(): ?int
            {
                return $this->count;
            }

            public function setPrice(float $price): void
            {
                $this->price = $price;
            }

            public function getPrice(): ?float
            {
                return $this->price;
            }

            public function setActive(bool $active): void
            {
                $this->active = $active;
            }

            public function isActive(): ?bool
            {
                return $this->active;
            }
        };

        $dbRow = [
            'count' => '42',
            'price' => '99.99',
            'active' => '1',
        ];

        $this->hydration->hydrate($entity, $dbRow);

        $this->assertSame(42, $entity->getCount());
        $this->assertSame(99.99, $entity->getPrice());
        $this->assertTrue($entity->isActive());
    }

    public function testApplySetsPropertiesWithoutUpdatingSnapshot(): void
    {
        $entity = new class {
            private ?int $id = null;
            private ?string $name = null;
            private ?string $email = null;
            private array $__snapshot = [];

            public function getId(): ?int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function setName(string $name): void
            {
                $this->name = $name;
            }

            public function getEmail(): ?string
            {
                return $this->email;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        // Step 1: hydrate from DB — establishes snapshot
        $this->hydration->hydrate($entity, [
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertEmpty($this->hydration->getChanges($entity));

        // Step 2: apply programmatic change — snapshot untouched
        $result = $this->hydration->apply($entity, ['name' => 'Jane']);

        // Returns entity for chaining
        $this->assertSame($entity, $result);

        // Property is updated
        $this->assertSame('Jane', $entity->getName());

        // Snapshot still has original value
        $snapshot = $this->hydration->getSnapshot($entity);
        $this->assertSame('John', $snapshot['name']);

        // getChanges() detects the modification
        $changes = $this->hydration->getChanges($entity);
        $this->assertArrayHasKey('name', $changes);
        $this->assertSame('Jane', $changes['name']);

        // Unchanged fields not in changes
        $this->assertArrayNotHasKey('id', $changes);
        $this->assertArrayNotHasKey('email', $changes);
    }

    public function testApplyWithTypeCasting(): void
    {
        $entity = new class {
            private ?int $count = null;
            private ?bool $active = null;
            private array $__snapshot = [];

            public function getCount(): ?int
            {
                return $this->count;
            }

            public function isActive(): ?bool
            {
                return $this->active;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        // Hydrate with initial values
        $this->hydration->hydrate($entity, ['count' => '10', 'active' => '0']);
        $this->assertEmpty($this->hydration->getChanges($entity));

        // Apply with string values (as from DB) — type casting works same as hydrate
        $this->hydration->apply($entity, ['count' => '42', 'active' => '1']);

        $this->assertSame(42, $entity->getCount());
        $this->assertTrue($entity->isActive());

        // Snapshot still has original values
        $snapshot = $this->hydration->getSnapshot($entity);
        $this->assertSame(10, $snapshot['count']);
        $this->assertFalse($snapshot['active']);

        // Changes detected
        $changes = $this->hydration->getChanges($entity);
        $this->assertSame(42, $changes['count']);
        $this->assertTrue($changes['active']);
    }

    public function testApplyMultipleFieldsPreservesFullSnapshot(): void
    {
        $entity = new class {
            private ?string $firstName = null;
            private ?string $lastName = null;
            private ?string $email = null;
            private array $__snapshot = [];

            public function getFirstName(): ?string
            {
                return $this->firstName;
            }

            public function getLastName(): ?string
            {
                return $this->lastName;
            }

            public function getEmail(): ?string
            {
                return $this->email;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        // Hydrate full entity
        $this->hydration->hydrate($entity, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        // Apply changes to multiple fields
        $this->hydration->apply($entity, [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $this->assertSame('Jane', $entity->getFirstName());
        $this->assertSame('Smith', $entity->getLastName());
        $this->assertSame('john@example.com', $entity->getEmail());

        // All original snapshot values preserved
        $snapshot = $this->hydration->getSnapshot($entity);
        $this->assertSame('John', $snapshot['first_name']);
        $this->assertSame('Doe', $snapshot['last_name']);
        $this->assertSame('john@example.com', $snapshot['email']);

        // Only changed fields detected
        $changedFields = $this->hydration->getChangedFields($entity);
        $this->assertContains('first_name', $changedFields);
        $this->assertContains('last_name', $changedFields);
        $this->assertNotContains('email', $changedFields);
    }
}
