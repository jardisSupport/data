<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\DetectChanges;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateAggregate;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DetectChanges handler.
 */
class DetectChangesTest extends TestCase
{
    private DetectChanges $detectChanges;

    protected function setUp(): void
    {
        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $this->detectChanges = new DetectChanges(
            new GetSnapshot(),
            new GetPropertyValue($columnNameToPropertyName)
        );
    }

    public function testDetectsNoChangesWhenEntityUnmodified(): void
    {
        $entity = new class {
            private string $name = 'John';
            private int $age = 30;
            private array $__snapshot = ['name' => 'John', 'age' => 30];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertEmpty($changes);
    }

    public function testDetectsSinglePropertyChange(): void
    {
        $entity = new class {
            private string $name = 'Jane';
            private int $age = 30;
            private array $__snapshot = ['name' => 'John', 'age' => 30];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('name', $changes);
        $this->assertEquals('Jane', $changes['name']);
    }

    public function testDetectsMultiplePropertyChanges(): void
    {
        $entity = new class {
            private string $name = 'Jane';
            private int $age = 35;
            private array $__snapshot = ['name' => 'John', 'age' => 30];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(2, $changes);
        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('age', $changes);
        $this->assertEquals('Jane', $changes['name']);
        $this->assertEquals(35, $changes['age']);
    }

    public function testDetectsChangeToNull(): void
    {
        $entity = new class {
            private ?string $name = null;
            private array $__snapshot = ['name' => 'John'];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('name', $changes);
        $this->assertNull($changes['name']);
    }

    public function testDetectsChangeFromNull(): void
    {
        $entity = new class {
            private string $name = 'John';
            private array $__snapshot = ['name' => null];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('name', $changes);
        $this->assertEquals('John', $changes['name']);
    }

    public function testReturnsEmptyArrayWhenNoSnapshot(): void
    {
        $entity = new class {
            private string $name = 'John';
            private int $age = 30;
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertEmpty($changes);
    }

    public function testReturnsEmptyArrayWhenEmptySnapshot(): void
    {
        $entity = new class {
            private string $name = 'John';
            private array $__snapshot = [];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertEmpty($changes);
    }

    public function testDetectsDateTimeChange(): void
    {
        $entity = new class {
            private DateTime $updatedAt;
            private array $__snapshot = [];

            public function __construct()
            {
                $this->updatedAt = new DateTime('2024-01-20 10:00:00');
                // Snapshot stores scalar representation (as produced by HydrateEntity)
                $this->__snapshot = ['updated_at' => '2024-01-15 14:30:45'];
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('updated_at', $changes);
        $this->assertSame('2024-01-20 10:00:00', $changes['updated_at']);
    }

    public function testNoFalsePositiveWithSameDateTimeValue(): void
    {
        $entity = new class {
            private DateTime $updatedAt;
            private array $__snapshot = [];

            public function __construct()
            {
                $this->updatedAt = new DateTime('2024-01-15 14:30:45');
                // Same timestamp as string — no change should be detected
                $this->__snapshot = ['updated_at' => '2024-01-15 14:30:45'];
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertEmpty($changes);
    }

    public function testDetectsArrayChange(): void
    {
        $entity = new class {
            private array $tags = ['php', 'mysql'];
            private array $__snapshot = ['tags' => ['php', 'testing']];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('tags', $changes);
        $this->assertEquals(['php', 'mysql'], $changes['tags']);
    }

    public function testUsesStrictComparison(): void
    {
        $entity = new class {
            private int $count = 10;
            private array $__snapshot = ['count' => '10'];

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('count', $changes);
        $this->assertEquals(10, $changes['count']);
    }

    public function testNoFalsePositiveChangesAfterAggregateHydration(): void
    {
        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $setSnapshot = new SetSnapshot();
        $getPropertyValue = new GetPropertyValue($columnNameToPropertyName);

        $hydrateAggregate = new HydrateAggregate(
            $columnNameToPropertyName,
            new TypeCaster(),
            $setSnapshot,
            new GetSnapshot(),
            $getPropertyValue
        );

        $counter = new Counter();
        $hydrateAggregate->__invoke($counter, [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'client_identifier' => 'CLIENT-001',
            'counter_gateway' => [
                'id' => 3,
                'counter_id' => 1,
                'active_from' => '2024-01-01 00:00:00',
            ],
            'counter_register' => [
                ['id' => 10, 'counter_id' => 1, 'register_id' => 5],
            ],
        ]);

        $changes = $this->detectChanges->__invoke($counter);
        $this->assertEmpty($changes, 'No changes should be detected after aggregate hydration');

        $counter->setIdentifier('xyz-999');

        $changes = $this->detectChanges->__invoke($counter);
        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('identifier', $changes);
        $this->assertSame('xyz-999', $changes['identifier']);

        $this->assertArrayNotHasKey('counter_gateway', $changes);
        $this->assertArrayNotHasKey('counter_register', $changes);
    }

    public function testNoFalsePositiveWithBackedEnum(): void
    {
        $entity = new class {
            private \JardisSupport\Data\Tests\Unit\Fixtures\GatewayType $type;
            private array $__snapshot = [];

            public function __construct()
            {
                $this->type = \JardisSupport\Data\Tests\Unit\Fixtures\GatewayType::Electricity;
                // Scalar snapshot as produced by HydrateEntity
                $this->__snapshot = ['type' => 'ELECTRICITY'];
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertEmpty($changes);
    }

    public function testDetectsBackedEnumChange(): void
    {
        $entity = new class {
            private \JardisSupport\Data\Tests\Unit\Fixtures\GatewayType $type;
            private array $__snapshot = [];

            public function __construct()
            {
                $this->type = \JardisSupport\Data\Tests\Unit\Fixtures\GatewayType::Gas;
                $this->__snapshot = ['type' => 'ELECTRICITY'];
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('type', $changes);
        $this->assertSame('GAS', $changes['type']);
    }

    public function testNoFalsePositiveWithDateTimeImmutable(): void
    {
        $entity = new class {
            private \DateTimeImmutable $activeFrom;
            private array $__snapshot = [];

            public function __construct()
            {
                $this->activeFrom = new \DateTimeImmutable('2024-01-01 00:00:00');
                // Scalar snapshot as produced by HydrateEntity
                $this->__snapshot = ['active_from' => '2024-01-01 00:00:00'];
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertEmpty($changes);
    }
}
