<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\IsHydratableColumn;
use JardisSupport\Data\Handler\MergeSnapshot;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\ToSnapshotValue;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\Gateway;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MergeSnapshot handler.
 */
class MergeSnapshotTest extends TestCase
{
    private MergeSnapshot $mergeSnapshot;
    private GetSnapshot $getSnapshot;

    protected function setUp(): void
    {
        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $this->getSnapshot = new GetSnapshot();
        $this->mergeSnapshot = new MergeSnapshot(
            $this->getSnapshot,
            new SetSnapshot(),
            new IsHydratableColumn($columnNameToPropertyName),
            $columnNameToPropertyName,
            new TypeCaster(),
            new ToSnapshotValue()
        );
    }

    public function testMergesCastValuesWithoutSettingProperties(): void
    {
        $counter = new Counter();

        ($this->mergeSnapshot)($counter, ['id' => '7', 'active_from' => '2026-01-01', 'active_until' => null]);

        $this->assertSame(
            ['id' => 7, 'active_from' => '2026-01-01 00:00:00', 'active_until' => null],
            ($this->getSnapshot)($counter)
        );
        $this->assertNull($counter->getId());
        $this->assertNull($counter->getActiveFrom());
    }

    public function testKeepsSnapshotColumnsNotInData(): void
    {
        $counter = new Counter();
        ($this->mergeSnapshot)($counter, ['identifier' => 'c-1', 'counter_number' => 'B']);

        ($this->mergeSnapshot)($counter, ['counter_number' => 'A']);

        $this->assertSame(['identifier' => 'c-1', 'counter_number' => 'A'], ($this->getSnapshot)($counter));
    }

    public function testStoresBackedEnumAsBackingValue(): void
    {
        $fromText = new Gateway();
        $fromEnum = new Gateway();

        ($this->mergeSnapshot)($fromText, ['type' => 'GAS']);
        ($this->mergeSnapshot)($fromEnum, ['type' => GatewayType::Electricity]);

        $this->assertSame(['type' => 'GAS'], ($this->getSnapshot)($fromText));
        $this->assertSame(['type' => 'ELECTRICITY'], ($this->getSnapshot)($fromEnum));
        $this->assertNull($fromText->getType());
        $this->assertNull($fromEnum->getType());
    }

    public function testSkipsRelationData(): void
    {
        $counter = new Counter();

        ($this->mergeSnapshot)($counter, [
            'counter_number' => 'N1',
            'counter_register' => [['id' => 1], ['id' => 2]],
            'counter_gateway' => ['id' => 3],
        ]);

        $this->assertSame(['counter_number' => 'N1'], ($this->getSnapshot)($counter));
    }

    public function testRecordsColumnWithoutPropertyAsNull(): void
    {
        $counter = new Counter();

        ($this->mergeSnapshot)($counter, ['unknown_column' => 'x']);

        $this->assertSame(['unknown_column' => null], ($this->getSnapshot)($counter));
    }

    public function testEntityWithoutSnapshotPropertyStaysUntouched(): void
    {
        $entity = new class {
            public ?string $name = 'kept';
        };

        ($this->mergeSnapshot)($entity, ['name' => 'other']);

        $this->assertSame('kept', $entity->name);
        $this->assertSame([], ($this->getSnapshot)($entity));
    }
}
