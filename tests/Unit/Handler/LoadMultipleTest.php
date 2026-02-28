<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\CloneEntity;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetSnapshot;
use JardisSupport\Data\Handler\HydrateEntity;
use JardisSupport\Data\Handler\LoadMultiple;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\SetSnapshot;
use JardisSupport\Data\Handler\TypeCaster;
use PHPUnit\Framework\TestCase;

class LoadMultipleTest extends TestCase
{
    private LoadMultiple $loadMultiple;

    protected function setUp(): void
    {
        $setSnapshot = new SetSnapshot();
        $getSnapshot = new GetSnapshot();
        $columnNameToPropertyName = new ColumnNameToPropertyName();
        $typeCaster = new TypeCaster();

        $this->loadMultiple = new LoadMultiple(
            new CloneEntity($getSnapshot, $setSnapshot),
            new HydrateEntity(
                new SetPropertyValue($columnNameToPropertyName, $typeCaster),
                $setSnapshot
            )
        );
    }

    public function testLoadsMultipleRows(): void
    {
        $template = new class {
            private ?int $id = null;
            private ?string $name = null;
            private array $__snapshot = [];

            public function getId(): ?int
            {
                return $this->id;
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

        $entities = $this->loadMultiple->__invoke($template, $rows);

        $this->assertCount(3, $entities);
        $this->assertEquals(1, $entities[0]->getId());
        $this->assertEquals('User 1', $entities[0]->getName());
        $this->assertEquals(2, $entities[1]->getId());
        $this->assertEquals('User 2', $entities[1]->getName());
        $this->assertEquals(3, $entities[2]->getId());
        $this->assertEquals('User 3', $entities[2]->getName());
    }

    public function testReturnsEmptyArrayForEmptyRows(): void
    {
        $template = new class {
            private ?int $id = null;
        };

        $entities = $this->loadMultiple->__invoke($template, []);

        $this->assertEmpty($entities);
    }

    public function testEntitiesAreIndependent(): void
    {
        $template = new class {
            private ?string $name = null;
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }

            public function setName(string $name): void
            {
                $this->name = $name;
            }
        };

        $rows = [
            ['name' => 'User 1'],
            ['name' => 'User 2'],
        ];

        $entities = $this->loadMultiple->__invoke($template, $rows);

        // Modify first entity
        $entities[0]->setName('Modified');

        // Second entity should be unchanged
        $this->assertEquals('Modified', $entities[0]->getName());
        $this->assertEquals('User 2', $entities[1]->getName());
    }

    public function testTemplateRemainsUnchanged(): void
    {
        $template = new class {
            private ?string $name = 'template';
            private array $__snapshot = [];

            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $rows = [
            ['name' => 'User 1'],
        ];

        $this->loadMultiple->__invoke($template, $rows);

        $this->assertEquals('template', $template->getName());
    }

    public function testHandlesSnakeCaseColumns(): void
    {
        $template = new class {
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

        $rows = [
            ['first_name' => 'John', 'last_name' => 'Doe'],
        ];

        $entities = $this->loadMultiple->__invoke($template, $rows);

        $this->assertEquals('John', $entities[0]->getFirstName());
        $this->assertEquals('Doe', $entities[0]->getLastName());
    }
}
