<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit;

use JardisSupport\Data\FieldMapper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FieldMapper — bidirectional array-key mapping.
 */
class FieldMapperTest extends TestCase
{
    private FieldMapper $fieldMapper;

    /** @var array<string, string> */
    private array $map = [
        'customerName' => 'name',
        'orderNumber'  => 'order_number',
        'postalCode'   => 'postal_code',
    ];

    protected function setUp(): void
    {
        $this->fieldMapper = new FieldMapper();
    }

    // --- toColumns ---

    public function testToColumnsRenamesKeys(): void
    {
        $input = ['customerName' => 'Müller', 'orderNumber' => 'O-123'];

        $result = $this->fieldMapper->toColumns($input, $this->map);

        $this->assertSame(['name' => 'Müller', 'order_number' => 'O-123'], $result);
    }

    public function testToColumnsWithEmptyMapReturnsIdentity(): void
    {
        $input = ['customerName' => 'Müller', 'orderNumber' => 'O-123'];

        $result = $this->fieldMapper->toColumns($input, []);

        $this->assertSame($input, $result);
    }

    public function testToColumnsPassesThroughUnmappedKeys(): void
    {
        $input = ['customerName' => 'Müller', 'email' => 'a@b.de', 'age' => 42];

        $result = $this->fieldMapper->toColumns($input, $this->map);

        $this->assertSame(['name' => 'Müller', 'email' => 'a@b.de', 'age' => 42], $result);
    }

    public function testToColumnsWithEmptyData(): void
    {
        $result = $this->fieldMapper->toColumns([], $this->map);

        $this->assertSame([], $result);
    }

    // --- fromColumns ---

    public function testFromColumnsRenamesKeys(): void
    {
        $input = ['name' => 'Müller', 'order_number' => 'O-123'];

        $result = $this->fieldMapper->fromColumns($input, $this->map);

        $this->assertSame(['customerName' => 'Müller', 'orderNumber' => 'O-123'], $result);
    }

    public function testFromColumnsWithEmptyMapReturnsIdentity(): void
    {
        $input = ['name' => 'Müller', 'order_number' => 'O-123'];

        $result = $this->fieldMapper->fromColumns($input, []);

        $this->assertSame($input, $result);
    }

    public function testFromColumnsPassesThroughUnmappedKeys(): void
    {
        $input = ['name' => 'Müller', 'email' => 'a@b.de', 'age' => 42];

        $result = $this->fieldMapper->fromColumns($input, $this->map);

        $this->assertSame(['customerName' => 'Müller', 'email' => 'a@b.de', 'age' => 42], $result);
    }

    public function testFromColumnsRecursiveOnNestedArrays(): void
    {
        $input = [
            'order_number' => 'O-123',
            'customer' => [
                'name' => 'Müller',
                'billing_address' => [
                    'postal_code' => '12345',
                    'city' => 'Berlin',
                ],
            ],
        ];

        $result = $this->fieldMapper->fromColumns($input, $this->map);

        $this->assertSame([
            'orderNumber' => 'O-123',
            'customer' => [
                'customerName' => 'Müller',
                'billing_address' => [
                    'postalCode' => '12345',
                    'city' => 'Berlin',
                ],
            ],
        ], $result);
    }

    public function testFromColumnsWithEmptyData(): void
    {
        $result = $this->fieldMapper->fromColumns([], $this->map);

        $this->assertSame([], $result);
    }

    // --- Symmetrie ---

    public function testSymmetryFromColumnsRevertsToColumns(): void
    {
        $original = ['customerName' => 'Müller', 'orderNumber' => 'O-123', 'email' => 'a@b.de'];

        $columns = $this->fieldMapper->toColumns($original, $this->map);
        $restored = $this->fieldMapper->fromColumns($columns, $this->map);

        $this->assertSame($original, $restored);
    }

    // --- fromAggregate ---

    public function testFromAggregateMapsFlatEntity(): void
    {
        $data = ['order_number' => 'O-123', 'status' => 'pending'];
        $mapProvider = fn(string $entity) => match ($entity) {
            'order' => ['orderNumber' => 'order_number', 'status' => 'status'],
        };

        $result = $this->fieldMapper->fromAggregate($data, $mapProvider, 'order');

        $this->assertSame(['orderNumber' => 'O-123', 'status' => 'pending'], $result);
    }

    public function testFromAggregateFiltersUnmappedFields(): void
    {
        $data = ['id' => 1, 'order_number' => 'O-123', 'customer_id' => 5];
        $mapProvider = fn(string $entity) => ['orderNumber' => 'order_number'];

        $result = $this->fieldMapper->fromAggregate($data, $mapProvider, 'order');

        $this->assertSame(['orderNumber' => 'O-123'], $result);
    }

    public function testFromAggregateWithNestedEntity(): void
    {
        $data = [
            'order_number' => 'O-123',
            'customer' => [
                'id' => 5,
                'name' => 'Mueller',
                'email' => 'a@b.de',
            ],
        ];
        $mapProvider = fn(string $entity) => match ($entity) {
            'order' => ['orderNumber' => 'order_number'],
            'customer' => ['customerName' => 'name', 'email' => 'email'],
        };

        $result = $this->fieldMapper->fromAggregate($data, $mapProvider, 'order');

        $this->assertSame([
            'orderNumber' => 'O-123',
            'customer' => [
                'customerName' => 'Mueller',
                'email' => 'a@b.de',
            ],
        ], $result);
    }

    public function testFromAggregateWithCollection(): void
    {
        $data = [
            'order_number' => 'O-123',
            'item' => [
                ['id' => 20, 'product_identifier' => 'PROD-1', 'quantity' => 2],
                ['id' => 21, 'product_identifier' => 'PROD-2', 'quantity' => 1],
            ],
        ];
        $mapProvider = fn(string $entity) => match ($entity) {
            'order' => ['orderNumber' => 'order_number'],
            'item' => ['productIdentifier' => 'product_identifier', 'quantity' => 'quantity'],
        };

        $result = $this->fieldMapper->fromAggregate($data, $mapProvider, 'order');

        $this->assertSame([
            'orderNumber' => 'O-123',
            'item' => [
                ['productIdentifier' => 'PROD-1', 'quantity' => 2],
                ['productIdentifier' => 'PROD-2', 'quantity' => 1],
            ],
        ], $result);
    }

    public function testFromAggregateFullExample(): void
    {
        $data = [
            'id' => 1,
            'order_number' => 'O-123',
            'customer_id' => 5,
            'status' => 'pending',
            'customer' => [
                'id' => 5,
                'name' => 'Mueller',
                'email' => 'a@b.de',
            ],
            'item' => [
                ['id' => 20, 'product_identifier' => 'PROD-1', 'quantity' => 2],
            ],
        ];
        $mapProvider = fn(string $entity) => match ($entity) {
            'order' => ['orderNumber' => 'order_number', 'status' => 'status'],
            'customer' => ['customerName' => 'name', 'email' => 'email'],
            'item' => ['productIdentifier' => 'product_identifier', 'quantity' => 'quantity'],
        };

        $result = $this->fieldMapper->fromAggregate($data, $mapProvider, 'order');

        $this->assertSame([
            'orderNumber' => 'O-123',
            'status' => 'pending',
            'customer' => [
                'customerName' => 'Mueller',
                'email' => 'a@b.de',
            ],
            'item' => [
                ['productIdentifier' => 'PROD-1', 'quantity' => 2],
            ],
        ], $result);
    }

    public function testFromAggregateWithEmptyData(): void
    {
        $mapProvider = fn(string $entity) => ['field' => 'column'];

        $result = $this->fieldMapper->fromAggregate([], $mapProvider, 'order');

        $this->assertSame([], $result);
    }

    public function testFromAggregateWithEmptyMapping(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $mapProvider = fn(string $entity) => [];

        $result = $this->fieldMapper->fromAggregate($data, $mapProvider, 'order');

        $this->assertSame([], $result);
    }
}
