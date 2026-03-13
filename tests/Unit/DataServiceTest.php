<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit;

use JardisSupport\Data\DataService;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for DataService
 *
 * Tests DataService with real entity hydration and change tracking
 */
class DataServiceTest extends TestCase
{
    private DataService $dataService;

    protected function setUp(): void
    {
        $this->dataService = new DataService();
    }

    public function testFullEntityLifecycleWithRealData(): void
    {
        // Create a realistic entity
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

        // Simulate database row
        $dbRow = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        // 1. Hydrate from database
        $this->dataService->hydrate($user, $dbRow);

        $this->assertSame(1, $user->getId());
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertSame(30, $user->getAge());

        // 2. Verify snapshot was created
        $snapshot = $this->dataService->getSnapshot($user);
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertSame(1, $snapshot['id']);

        // 3. Verify no changes initially
        $this->assertFalse($this->dataService->hasChanges($user));
        $this->assertEmpty($this->dataService->getChanges($user));

        // 4. Modify entity
        $user->setName('Jane Doe');
        $user->setAge(31);

        // 5. Detect changes
        $this->assertTrue($this->dataService->hasChanges($user));
        $changes = $this->dataService->getChanges($user);

        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('age', $changes);
        $this->assertSame('Jane Doe', $changes['name']);
        $this->assertSame(31, $changes['age']);
        $this->assertArrayNotHasKey('email', $changes); // Unchanged

        // 6. Get changed field names
        $changedFields = $this->dataService->getChangedFields($user);
        $this->assertContains('name', $changedFields);
        $this->assertContains('age', $changedFields);
        $this->assertNotContains('email', $changedFields);

        // 7. Convert to array
        $array = $this->dataService->toArray($user);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Jane Doe', $array['name']);
        $this->assertSame(31, $array['age']);

        // 8. Clone entity
        $clone = $this->dataService->clone($user);
        $this->assertNotSame($user, $clone);
        $this->assertSame($user->getName(), $clone->getName());

        // 9. Modify clone independently
        $clone->setName('Bob');
        $this->assertNotSame($user->getName(), $clone->getName());

        // 10. Diff entities
        $diff = $this->dataService->diff($user, $clone);
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

        $entities = $this->dataService->loadMultiple($template, $rows);

        $this->assertCount(3, $entities);
        $this->assertSame(1, $entities[0]->getId());
        $this->assertSame('User 1', $entities[0]->getName());
        $this->assertSame(2, $entities[1]->getId());
        $this->assertSame('User 2', $entities[1]->getName());

        // Each entity should be independent
        $entities[0]->setName('Modified');
        $this->assertNotSame($entities[0]->getName(), $entities[1]->getName());
    }

    public function testUpdatePropertiesPreservesSnapshot(): void
    {
        $entity = new class {
            private ?string $status = null;
            private ?int $count = null;
            private array $__snapshot = [];

            public function setStatus(string $status): void
            {
                $this->status = $status;
            }

            public function getStatus(): ?string
            {
                return $this->status;
            }

            public function setCount(int $count): void
            {
                $this->count = $count;
            }

            public function getCount(): ?int
            {
                return $this->count;
            }
        };

        // Initial hydration
        $this->dataService->hydrate($entity, ['status' => 'pending', 'count' => 0]);

        // Update properties without changing snapshot
        $this->dataService->updateProperties($entity, ['status' => 'active', 'count' => 5]);

        // Entity should be updated
        $this->assertSame('active', $entity->getStatus());
        $this->assertSame(5, $entity->getCount());

        // Snapshot should remain unchanged
        $snapshot = $this->dataService->getSnapshot($entity);
        $this->assertSame('pending', $snapshot['status']);
        $this->assertSame(0, $snapshot['count']);

        // Changes should be detected
        $this->assertTrue($this->dataService->hasChanges($entity));
        $changes = $this->dataService->getChanges($entity);
        $this->assertSame('active', $changes['status']);
        $this->assertSame(5, $changes['count']);
    }

    public function testHydrateFromArrayWithSimpleData(): void
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

        $this->dataService->hydrateFromArray($entity, $data);

        $this->assertSame('Test Entity', $entity->getName());
        $this->assertSame('Test Description', $entity->getDescription());
    }

    public function testGenerateUuid4(): void
    {
        $uuid = $this->dataService->generateUuid4();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateUuid7(): void
    {
        $uuid = $this->dataService->generateUuid7();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
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
        $this->dataService->hydrateFromArray($aggregate, $data);

        // No changes after hydration
        $this->assertFalse($this->dataService->hasChanges($aggregate));

        // Modify and detect
        $aggregate->setName('Modified');
        $this->assertTrue($this->dataService->hasChanges($aggregate));
        $changes = $this->dataService->getChanges($aggregate);
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

        // Simulate database returning strings (PDO default)
        $dbRow = [
            'count' => '42',
            'price' => '99.99',
            'active' => '1',
        ];

        $this->dataService->hydrate($entity, $dbRow);

        // After hydration, no changes should be detected (snapshot = typed values)
        $this->assertFalse($this->dataService->hasChanges($entity));
        $this->assertEmpty($this->dataService->getChanges($entity));

        // Snapshot should contain typed values, not raw strings
        $snapshot = $this->dataService->getSnapshot($entity);
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

        // Simulate database returning strings
        $dbRow = [
            'count' => '42',
            'price' => '99.99',
            'active' => '1',
        ];

        $this->dataService->hydrate($entity, $dbRow);

        $this->assertSame(42, $entity->getCount());
        $this->assertSame(99.99, $entity->getPrice());
        $this->assertTrue($entity->isActive());
    }
}
