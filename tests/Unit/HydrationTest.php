<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit;

use DateTimeImmutable;
use JardisSupport\Contract\Data\Absent;
use JardisSupport\Data\FieldMapper;
use JardisSupport\Data\Hydration;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGateway;
use JardisSupport\Data\Tests\Unit\Fixtures\Customer;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use JardisSupport\Data\Tests\Unit\Handler\HydrateAggregateTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
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

    public function testApplySkipsNullForKeyInSkipList(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $this->hydration->apply($entity, ['name' => 'Jane', 'password_hash' => null], ['password_hash']);

        $this->assertSame('Jane', $entity->getName());
        $this->assertSame('secret', $entity->getPasswordHash());
        $this->assertSame(['name' => 'Jane'], $this->hydration->getChanges($entity));
    }

    public function testApplyWritesValueForKeyInSkipList(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $this->hydration->apply($entity, ['password_hash' => 'new'], ['password_hash']);

        $this->assertSame('new', $entity->getPasswordHash());
        $this->assertSame(['password_hash' => 'new'], $this->hydration->getChanges($entity));
    }

    public function testApplyWritesNullForKeyNotInSkipList(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $this->hydration->apply($entity, ['name' => null, 'password_hash' => null], ['password_hash']);

        $this->assertNull($entity->getName());
        $this->assertSame('secret', $entity->getPasswordHash());
        $this->assertArrayHasKey('name', $this->hydration->getChanges($entity));
    }

    public function testApplyWithEmptySkipListWritesNullLikeBefore(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $this->hydration->apply($entity, ['password_hash' => null], []);

        $this->assertNull($entity->getPasswordHash());
        $this->assertSame(['password_hash' => null], $this->hydration->getChanges($entity));
    }

    public function testApplyLeavesPropertyUntouchedWhenKeyIsMissing(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $this->hydration->apply($entity, ['name' => 'Jane']);

        $this->assertSame('Jane', $entity->getName());
        $this->assertSame('secret', $entity->getPasswordHash());
        $this->assertSame(['name' => 'Jane'], $this->hydration->getChanges($entity));
    }

    public function testApplyNeverWritesAbsentEvenWhenPassedDirectly(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $this->hydration->apply($entity, ['name' => Absent::Value, 'password_hash' => Absent::Value]);

        $this->assertSame('John', $entity->getName());
        $this->assertSame('secret', $entity->getPasswordHash());
        $this->assertSame([], $this->hydration->getChanges($entity));
    }

    public function testApplyOfToColumnsOutputNeverWritesAbsent(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $columns = (new FieldMapper())->toColumns(
            ['name' => 'Jane', 'passwordHash' => Absent::Value],
            ['passwordHash' => 'password_hash'],
        );
        $this->hydration->apply($entity, $columns);

        $this->assertSame('Jane', $entity->getName());
        $this->assertSame('secret', $entity->getPasswordHash());
        $this->assertSame(['name' => 'Jane'], $this->hydration->getChanges($entity));
    }

    public function testApplyOfToColumnsOutputWritesDeliveredNull(): void
    {
        $entity = $this->skipEntity();
        $this->hydration->hydrate($entity, ['id' => 1, 'name' => 'John', 'password_hash' => 'secret']);

        $columns = (new FieldMapper())->toColumns(
            ['name' => Absent::Value, 'passwordHash' => null],
            ['passwordHash' => 'password_hash'],
        );
        $this->hydration->apply($entity, $columns);

        $this->assertSame('John', $entity->getName());
        $this->assertNull($entity->getPasswordHash());
    }

    private function skipEntity(): object
    {
        return new class {
            private ?int $id = null;
            private ?string $name = null;
            private ?string $passwordHash = null;
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getPasswordHash(): ?string
            {
                return $this->passwordHash;
            }
        };
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

    /**
     * Contract A6-L1: value B, snapshot B, merging A leaves the value at B, sets the
     * snapshot to A and getChanges() reports the column. Other snapshot columns stay.
     */
    public function testMergeSnapshotKeepsValueAndReportsColumnAsChanged(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['identifier' => 'c-1', 'counter_number' => 'B']);

        $result = $this->hydration->mergeSnapshot($counter, ['counter_number' => 'A']);

        $this->assertSame($counter, $result);
        $this->assertSame('B', $counter->getCounterNumber());
        $this->assertSame('c-1', $counter->getIdentifier());
        $this->assertSame(
            ['identifier' => 'c-1', 'counter_number' => 'A'],
            $this->hydration->getSnapshot($counter)
        );
        $this->assertSame(['counter_number' => 'B'], $this->hydration->getChanges($counter));
    }

    /**
     * Contract A6-L2: value A, snapshot B, merging A aligns the snapshot with the
     * value, so getChanges() no longer reports the column.
     */
    public function testMergeSnapshotMatchingValueClearsChange(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['counter_number' => 'B']);
        $this->hydration->apply($counter, ['counter_number' => 'A']);

        $this->hydration->mergeSnapshot($counter, ['counter_number' => 'A']);

        $this->assertSame('A', $counter->getCounterNumber());
        $this->assertSame(['counter_number' => 'A'], $this->hydration->getSnapshot($counter));
        $this->assertSame([], $this->hydration->getChanges($counter));
    }

    /**
     * Contract A6-L3: a date column merged as text or as object lands in the snapshot
     * in the same form hydrate() gives a twin entity for the same data; the property
     * itself stays untouched.
     */
    #[DataProvider('dateColumnProvider')]
    public function testMergeSnapshotTypesDateColumnLikeHydrate(mixed $activeFrom): void
    {
        $twin = new Counter();
        $this->hydration->hydrate($twin, ['active_from' => $activeFrom]);
        $counter = new Counter();

        $this->hydration->mergeSnapshot($counter, ['active_from' => $activeFrom]);

        $this->assertSame($this->hydration->getSnapshot($twin), $this->hydration->getSnapshot($counter));
        $this->assertIsString($this->hydration->getSnapshot($counter)['active_from']);
        $this->assertNull($counter->getActiveFrom());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function dateColumnProvider(): array
    {
        return [
            'text' => ['2026-01-01 10:00:00'],
            'text with offset and fraction' => ['2026-01-01 10:00:00.123456+02:00'],
            'object' => [new DateTimeImmutable('2026-01-01 10:00:00')],
        ];
    }

    /**
     * Contract B3.1-N1: through the facade, an object relation delivered as null (snake_case or
     * camelCase key) reads like a twin hydrated without the key in every snapshot reader.
     */
    #[DataProvider('nullCounterGatewayKeyProvider')]
    public function testHydrateAggregateNullObjectRelationReadsLikeTwin(string $key): void
    {
        $base = ['id' => 1, 'identifier' => 'C-1', 'counter_number' => 'N-1'];
        $twin = new Counter();
        $this->hydration->hydrateAggregate($twin, $base);
        $probe = new Counter();
        $this->hydration->hydrateAggregate($probe, $base + ['counter_gateway' => ['id' => 3]]);
        $counter = new Counter();

        $this->hydration->hydrateAggregate($counter, $base + [$key => null]);

        $this->assertNull($counter->getCounterGateway());
        $this->assertReadsLikeTwin($counter, $twin, $probe);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nullCounterGatewayKeyProvider(): array
    {
        return [
            'snake_case key' => ['counter_gateway'],
            'camelCase key' => ['counterGateway'],
        ];
    }

    /**
     * Contract B3.1-N1: the same for an object relation without a setter.
     */
    public function testHydrateAggregateNullObjectRelationWithoutSetterReadsLikeTwin(): void
    {
        $base = ['id' => 'cust-1', 'name' => 'Jane', 'email' => 'jane@example.com'];
        $twin = new Customer();
        $this->hydration->hydrateAggregate($twin, $base);
        $probe = new Customer();
        $this->hydration->hydrateAggregate(
            $probe,
            $base + ['billing_address' => ['street' => 'Main 1', 'city' => 'Town', 'zip_code' => '12345']]
        );
        $customer = new Customer();

        $this->hydration->hydrateAggregate($customer, $base + ['billing_address' => null]);

        $this->assertNull($customer->getBillingAddress());
        $this->assertReadsLikeTwin($customer, $twin, $probe);
    }

    /**
     * Contract B3.1-N2: one level deeper, the single child reads like its twin, the null column
     * of the child stays, and the root graph is unchanged.
     */
    public function testHydrateAggregateNullObjectRelationInChildReadsLikeTwin(): void
    {
        $child = ['id' => 3, 'active_until' => null];
        $twin = new Counter();
        $this->hydration->hydrateAggregate($twin, ['id' => 1, 'counter_gateway' => $child]);
        $probe = new Counter();
        $this->hydration->hydrateAggregate(
            $probe,
            ['id' => 1, 'counter_gateway' => $child + ['gateway' => ['id' => 100]]]
        );
        $counter = new Counter();

        $this->hydration->hydrateAggregate($counter, ['id' => 1, 'counter_gateway' => $child + ['gateway' => null]]);

        $this->assertNotNull($counter->getCounterGateway());
        $this->assertNotNull($twin->getCounterGateway());
        $this->assertNotNull($probe->getCounterGateway());
        $this->assertArrayHasKey('active_until', $this->hydration->getSnapshot($counter->getCounterGateway()));
        $this->assertReadsLikeTwin(
            $counter->getCounterGateway(),
            $twin->getCounterGateway(),
            $probe->getCounterGateway()
        );
        $this->assertSame($this->hydration->aggregateToArray($twin), $this->hydration->aggregateToArray($counter));
    }

    /**
     * Contract B3.1-N2: in a list element, the element reads like its twin.
     */
    public function testHydrateAggregateNullObjectRelationInListElementReadsLikeTwin(): void
    {
        $root = new class {
            /** @var \JardisSupport\Data\Tests\Unit\Fixtures\Customer[] */
            private array $customers = [];
            private array $__snapshot = [];

            /** @return array<int, Customer> */
            public function getCustomers(): array
            {
                return $this->customers;
            }
        };
        $element = ['id' => 'cust-1', 'name' => 'Jane', 'email' => 'jane@example.com'];
        $twin = clone $root;
        $this->hydration->hydrateAggregate($twin, ['customers' => [$element]]);
        $probe = clone $root;
        $this->hydration->hydrateAggregate(
            $probe,
            ['customers' => [$element + ['billing_address' => ['street' => 'Main 1', 'city' => 'Town']]]]
        );

        $this->hydration->hydrateAggregate($root, ['customers' => [$element + ['billing_address' => null]]]);

        $this->assertReadsLikeTwin($root->getCustomers()[0], $twin->getCustomers()[0], $probe->getCustomers()[0]);
        $this->assertSame($this->hydration->aggregateToArray($twin), $this->hydration->aggregateToArray($root));
    }

    /**
     * Contract B3.1-N3: a nullable list property with a resolvable element class, delivered as
     * null, reads like a twin without the key.
     */
    #[DataProviderExternal(HydrateAggregateTest::class, 'nullableListRelationProvider')]
    public function testHydrateAggregateNullListRelationReadsLikeTwin(object $prototype): void
    {
        $twin = clone $prototype;
        $this->hydration->hydrateAggregate($twin, ['name' => 'Order 1']);
        $probe = clone $prototype;
        $this->hydration->hydrateAggregate(
            $probe,
            ['name' => 'Order 1', 'items' => [['product_name' => 'A', 'quantity' => 1, 'price' => 1.5]]]
        );
        $aggregate = clone $prototype;

        $this->hydration->hydrateAggregate($aggregate, ['name' => 'Order 1', 'items' => null]);

        $this->assertReadsLikeTwin($aggregate, $twin, $probe);
    }

    /**
     * Contract B3.1-N4: setting the relation after a null delivery reports the same changes as
     * the twin that never got the key.
     */
    public function testSettingRelationAfterNullDeliveryReportsChangesLikeTwin(): void
    {
        $base = ['id' => 1, 'identifier' => 'C-1', 'counter_number' => 'N-1'];
        $twin = new Counter();
        $this->hydration->hydrateAggregate($twin, $base);
        $counter = new Counter();
        $this->hydration->hydrateAggregate($counter, $base + ['counter_gateway' => null]);

        $twin->setCounterGateway(new CounterGateway());
        $counter->setCounterGateway(new CounterGateway());

        $this->assertSame($this->hydration->getChanges($twin), $this->hydration->getChanges($counter));
    }

    /**
     * Contract B3.1-N5: null at a column property stays in the snapshot and in toArray(), as
     * before: scalar, date, enum, array without element class and untyped properties.
     */
    #[DataProvider('nullColumnProvider')]
    public function testHydrateAggregateKeepsNullColumnInSnapshot(object $entity, string $key): void
    {
        $this->hydration->hydrateAggregate($entity, [$key => null]);

        $this->assertSame([$key => null], $this->hydration->getSnapshot($entity));
        $this->assertSame([$key => null], $this->hydration->toArray($entity));
    }

    /**
     * Column properties without a setter, so null is assigned for every kind.
     *
     * @return array<string, array{object, string}>
     */
    public static function nullColumnProvider(): array
    {
        $columns = new class {
            private ?int $count = 1;
            private ?string $label = 'x';
            private ?GatewayType $type = GatewayType::Gas;
            private ?array $settings = ['a' => 1];
            private $free = 'x';
            private array $__snapshot = [];
        };

        return [
            '?int' => [clone $columns, 'count'],
            '?string' => [clone $columns, 'label'],
            '?DateTimeImmutable' => [new CounterGateway(), 'active_until'],
            '?BackedEnum' => [clone $columns, 'type'],
            '?array without element class' => [clone $columns, 'settings'],
            'untyped' => [clone $columns, 'free'],
        ];
    }

    /**
     * Asserts that an entity hydrated with a null relation key reads like its twin hydrated
     * without the key: snapshot, toArray, aggregateToArray, getChanges, and diff against a
     * probe whose relation is filled.
     */
    private function assertReadsLikeTwin(object $entity, object $twin, object $probe): void
    {
        $this->assertSame($this->hydration->getSnapshot($twin), $this->hydration->getSnapshot($entity));
        $this->assertSame($this->hydration->toArray($twin), $this->hydration->toArray($entity));
        $this->assertSame($this->hydration->aggregateToArray($twin), $this->hydration->aggregateToArray($entity));
        $this->assertSame($this->hydration->getChanges($twin), $this->hydration->getChanges($entity));
        $this->assertSame($this->hydration->diff($twin, $probe), $this->hydration->diff($entity, $probe));
    }
}
