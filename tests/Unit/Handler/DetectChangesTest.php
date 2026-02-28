<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\DetectChanges;
use JardisSupport\Data\Handler\GetPropertyValue;
use JardisSupport\Data\Handler\GetSnapshot;
use PHPUnit\Framework\TestCase;

class DetectChangesTest extends TestCase
{
    private DetectChanges $detectChanges;

    protected function setUp(): void
    {
        $this->detectChanges = new DetectChanges(
            new GetSnapshot(),
            new GetPropertyValue(new ColumnNameToPropertyName())
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
                $this->__snapshot = ['updated_at' => new DateTime('2024-01-15 14:30:45')];
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $changes = $this->detectChanges->__invoke($entity);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('updated_at', $changes);
        $this->assertInstanceOf(DateTime::class, $changes['updated_at']);
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

        // Should detect difference because of strict comparison (int 10 !== string '10')
        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('count', $changes);
        $this->assertEquals(10, $changes['count']);
    }
}
