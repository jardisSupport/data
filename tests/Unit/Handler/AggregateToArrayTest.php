<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTimeImmutable;
use JardisSupport\Data\Handler\AggregateToArray;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Hydration;
use JardisSupport\Data\Tests\Unit\Fixtures\Counter;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGateway;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterGatewayRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\CounterRegister;
use JardisSupport\Data\Tests\Unit\Fixtures\Gateway;
use JardisSupport\Data\Tests\Unit\Fixtures\GatewayType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AggregateToArray handler (full object graph, snapshot-based keys).
 */
class AggregateToArrayTest extends TestCase
{
    private AggregateToArray $aggregateToArray;
    private Hydration $hydration;

    protected function setUp(): void
    {
        $this->aggregateToArray = new AggregateToArray(
            new GetSnapshot(),
            new ColumnNameToPropertyName()
        );
        $this->hydration = new Hydration();
    }

    public function testUsesSnapshotColumnNamesAsKeys(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
        ]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('identifier', $result);
        $this->assertArrayHasKey('counter_number', $result);
        $this->assertArrayNotHasKey('counterNumber', $result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('abc-123', $result['identifier']);
        $this->assertEquals('CNT-001', $result['counter_number']);
    }

    public function testPreservesCamelCaseColumnNames(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, [
            'id' => 1,
            'clientIdentifier' => 'CLI-001',
        ]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayHasKey('clientIdentifier', $result);
        $this->assertArrayNotHasKey('client_identifier', $result);
        $this->assertEquals('CLI-001', $result['clientIdentifier']);
    }

    public function testRelationPropertyNamesStayCamelCase(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1]);

        $counterGateway = new CounterGateway();
        $this->hydration->hydrate($counterGateway, ['id' => 3, 'counter_id' => 1]);

        $counter->setCounterGateway($counterGateway);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayHasKey('counterGateway', $result);
        $this->assertArrayNotHasKey('counter_gateway', $result);
        $this->assertIsArray($result['counterGateway']);
        $this->assertEquals(3, $result['counterGateway']['id']);
    }

    public function testManyRelationPropertyNamesStayCamelCase(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1]);

        $reg1 = new CounterRegister();
        $this->hydration->hydrate($reg1, ['id' => 10, 'counter_id' => 1]);

        $reg2 = new CounterRegister();
        $this->hydration->hydrate($reg2, ['id' => 20, 'counter_id' => 1]);

        $counter->addCounterRegister($reg1);
        $counter->addCounterRegister($reg2);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayHasKey('counterRegister', $result);
        $this->assertArrayNotHasKey('counter_register', $result);
        $this->assertCount(2, $result['counterRegister']);
        $this->assertEquals(10, $result['counterRegister'][0]['id']);
        $this->assertEquals(20, $result['counterRegister'][1]['id']);
    }

    public function testNestedEntityUsesOwnSnapshot(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1, 'counter_number' => 'CNT-001']);

        $counterGateway = new CounterGateway();
        $this->hydration->hydrate($counterGateway, ['id' => 3, 'counter_id' => 1]);

        $gateway = new Gateway();
        $this->hydration->hydrate($gateway, ['id' => 100, 'identifier' => 'GW-001']);

        $counterGateway->setGateway($gateway);
        $counter->setCounterGateway($counterGateway);

        $result = ($this->aggregateToArray)($counter);

        // Root: snapshot keys
        $this->assertEquals('CNT-001', $result['counter_number']);

        // Nested ONE: property name as key, entity uses own snapshot keys
        $this->assertArrayHasKey('counterGateway', $result);
        $this->assertEquals(3, $result['counterGateway']['id']);
        $this->assertEquals(1, $result['counterGateway']['counter_id']);

        // Deeply nested ONE
        $this->assertArrayHasKey('gateway', $result['counterGateway']);
        $this->assertEquals(100, $result['counterGateway']['gateway']['id']);
        $this->assertEquals('GW-001', $result['counterGateway']['gateway']['identifier']);
    }

    public function testHandlesNullValues(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1, 'active_until' => null]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertNull($result['active_until']);
    }

    public function testFormatsDateTimeToString(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, [
            'id' => 1,
            'active_from' => '2024-01-15 14:30:45',
        ]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertEquals('2024-01-15 14:30:45', $result['active_from']);
    }

    public function testFormatsBackedEnumAsValue(): void
    {
        $gateway = new Gateway();
        $this->hydration->hydrate($gateway, [
            'id' => 1,
            'identifier' => 'GW-001',
            'type' => 'ELECTRICITY',
        ]);

        $result = ($this->aggregateToArray)($gateway);

        $this->assertSame('ELECTRICITY', $result['type']);
    }

    public function testSkipsSnapshotProperty(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayNotHasKey('__snapshot', $result);
    }

    public function testSkipsUninitializedProperties(): void
    {
        $entity = new class {
            private string $initialized = 'value';
            private string $uninitialized;
            private array $__snapshot = ['initialized' => 'value'];
        };

        $result = ($this->aggregateToArray)($entity);

        $this->assertArrayHasKey('initialized', $result);
        $this->assertArrayNotHasKey('uninitialized', $result);
    }

    public function testNullRelationIncludedAsNull(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayHasKey('counterGateway', $result);
        $this->assertNull($result['counterGateway']);
    }

    public function testEmptyManyRelationIncludedAsEmptyArray(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, ['id' => 1]);

        $result = ($this->aggregateToArray)($counter);

        $this->assertArrayHasKey('counterRegister', $result);
        $this->assertEquals([], $result['counterRegister']);
    }

    public function testRoundTripHydrateToArray(): void
    {
        $data = [
            'id' => 1,
            'identifier' => 'abc-123',
            'counter_number' => 'CNT-001',
            'clientIdentifier' => 'CLI-001',
            'active_from' => '2024-06-01 08:00:00',
            'active_until' => null,
        ];

        $counter = new Counter();
        $this->hydration->hydrate($counter, $data);

        $result = ($this->aggregateToArray)($counter);

        // Scalar keys must match original column names exactly
        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $result, "Key '$key' missing in result");
            $this->assertEquals($value, $result[$key], "Value mismatch for key '$key'");
        }
    }

    public function testFullAggregateGraph(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, [
            'id' => 1,
            'identifier' => 'C-001',
            'counter_number' => 'CNT-001',
        ]);

        $counterGateway = new CounterGateway();
        $this->hydration->hydrate($counterGateway, [
            'id' => 10,
            'counter_id' => 1,
            'active_from' => '2024-01-01 00:00:00',
        ]);

        $gateway = new Gateway();
        $this->hydration->hydrate($gateway, [
            'id' => 100,
            'identifier' => 'GW-001',
            'type' => 'ELECTRICITY',
        ]);
        $counterGateway->setGateway($gateway);

        $cgr = new CounterGatewayRegister();
        $this->hydration->hydrate($cgr, [
            'id' => 50,
            'counter_gateway_id' => 10,
            'register_id' => 5,
        ]);
        $counterGateway->addCounterGatewayRegister($cgr);

        $counter->setCounterGateway($counterGateway);

        $reg = new CounterRegister();
        $this->hydration->hydrate($reg, [
            'id' => 20,
            'counter_id' => 1,
            'register_id' => 3,
            'related_type' => 'meter',
        ]);
        $counter->addCounterRegister($reg);

        $result = ($this->aggregateToArray)($counter);

        // Root scalars — snapshot keys
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('CNT-001', $result['counter_number']);

        // ONE relation — property name
        $this->assertArrayHasKey('counterGateway', $result);
        $this->assertEquals(10, $result['counterGateway']['id']);
        $this->assertEquals('2024-01-01 00:00:00', $result['counterGateway']['active_from']);

        // Nested ONE
        $this->assertArrayHasKey('gateway', $result['counterGateway']);
        $this->assertEquals('ELECTRICITY', $result['counterGateway']['gateway']['type']);

        // Nested MANY (CounterGatewayRegister)
        $this->assertArrayHasKey('counterGatewayRegister', $result['counterGateway']);
        $this->assertCount(1, $result['counterGateway']['counterGatewayRegister']);
        $this->assertEquals(50, $result['counterGateway']['counterGatewayRegister'][0]['id']);
        $this->assertEquals(10, $result['counterGateway']['counterGatewayRegister'][0]['counter_gateway_id']);

        // MANY relation — property name
        $this->assertArrayHasKey('counterRegister', $result);
        $this->assertCount(1, $result['counterRegister']);
        $this->assertEquals(20, $result['counterRegister'][0]['id']);
        $this->assertEquals('meter', $result['counterRegister'][0]['related_type']);
    }

    public function testReflectsCurrentValuesNotSnapshotValues(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, [
            'id' => 1,
            'counter_number' => 'CNT-001',
        ]);

        // Change via apply (no snapshot update)
        $this->hydration->apply($counter, ['counter_number' => 'CNT-002']);

        $result = ($this->aggregateToArray)($counter);

        // Must reflect the CURRENT value, not the snapshot value
        $this->assertEquals('CNT-002', $result['counter_number']);
    }

    public function testViaHydrationFacade(): void
    {
        $counter = new Counter();
        $this->hydration->hydrate($counter, [
            'id' => 1,
            'counter_number' => 'CNT-001',
        ]);

        $result = $this->hydration->aggregateToArray($counter);

        $this->assertArrayHasKey('counter_number', $result);
        $this->assertArrayNotHasKey('counterNumber', $result);
        $this->assertEquals('CNT-001', $result['counter_number']);
    }
}
