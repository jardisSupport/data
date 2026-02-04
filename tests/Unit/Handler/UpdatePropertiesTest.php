<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\TypeCaster;
use JardisSupport\Data\Handler\UpdateProperties;
use PHPUnit\Framework\TestCase;

class UpdatePropertiesTest extends TestCase
{
    private UpdateProperties $updateProperties;

    protected function setUp(): void
    {
        $this->updateProperties = new UpdateProperties(
            new SetPropertyValue(
                new ColumnNameToPropertyName(),
                new TypeCaster()
            )
        );
    }

    public function testUpdatesProperties(): void
    {
        $entity = new class {
            private ?string $name = 'original';
            private ?int $age = 25;

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getAge(): ?int
            {
                return $this->age;
            }
        };

        $this->updateProperties->__invoke($entity, ['name' => 'updated', 'age' => 30]);

        $this->assertEquals('updated', $entity->getName());
        $this->assertEquals(30, $entity->getAge());
    }

    public function testDoesNotAffectSnapshot(): void
    {
        $entity = new class {
            private ?string $status = 'pending';
            private array $__snapshot = ['status' => 'pending'];

            public function getStatus(): ?string
            {
                return $this->status;
            }

            public function getSnapshot(): array
            {
                return $this->__snapshot;
            }
        };

        $this->updateProperties->__invoke($entity, ['status' => 'active']);

        $this->assertEquals('active', $entity->getStatus());
        $this->assertEquals(['status' => 'pending'], $entity->getSnapshot());
    }

    public function testHandlesSnakeCaseColumns(): void
    {
        $entity = new class {
            private ?string $firstName = null;
            private ?string $lastName = null;

            public function getFirstName(): ?string
            {
                return $this->firstName;
            }

            public function getLastName(): ?string
            {
                return $this->lastName;
            }
        };

        $this->updateProperties->__invoke($entity, [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John', $entity->getFirstName());
        $this->assertEquals('Doe', $entity->getLastName());
    }

    public function testIgnoresUnknownProperties(): void
    {
        $entity = new class {
            private string $name = 'original';

            public function getName(): string
            {
                return $this->name;
            }
        };

        // Should not throw exception
        $this->updateProperties->__invoke($entity, [
            'name' => 'updated',
            'unknown' => 'value',
        ]);

        $this->assertEquals('updated', $entity->getName());
    }

    public function testUsesSetterWhenAvailable(): void
    {
        $entity = new class {
            private string $name;
            public bool $setterCalled = false;

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

        $this->updateProperties->__invoke($entity, ['name' => 'john']);

        $this->assertTrue($entity->setterCalled);
        $this->assertEquals('JOHN', $entity->getName());
    }

    public function testTypeCastsValues(): void
    {
        $entity = new class {
            private ?int $count = null;
            private ?bool $active = null;

            public function getCount(): ?int
            {
                return $this->count;
            }

            public function isActive(): ?bool
            {
                return $this->active;
            }
        };

        $this->updateProperties->__invoke($entity, [
            'count' => '42',
            'active' => '1',
        ]);

        $this->assertSame(42, $entity->getCount());
        $this->assertTrue($entity->isActive());
    }
}
