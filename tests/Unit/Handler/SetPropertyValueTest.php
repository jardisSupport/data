<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\SetPropertyValue;
use JardisSupport\Data\Handler\TypeCaster;
use PHPUnit\Framework\TestCase;

class SetPropertyValueTest extends TestCase
{
    private SetPropertyValue $setPropertyValue;
    private SetPropertyValue $setPropertyValueWithCaster;

    protected function setUp(): void
    {
        $this->setPropertyValue = new SetPropertyValue(
            new ColumnNameToPropertyName()
        );

        $this->setPropertyValueWithCaster = new SetPropertyValue(
            new ColumnNameToPropertyName(),
            new TypeCaster()
        );
    }

    public function testSetsPropertyViaReflection(): void
    {
        $entity = new class {
            private string $name;

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'name', 'John');

        $this->assertEquals('John', $entity->getName());
    }

    public function testSetsPropertyViaSetterMethod(): void
    {
        $entity = new class {
            private string $name;

            public function setName(string $name): void
            {
                $this->name = strtoupper($name);
            }

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'name', 'john');

        // Setter transforms value to uppercase
        $this->assertEquals('JOHN', $entity->getName());
    }

    public function testHandlesSnakeCaseColumnName(): void
    {
        $entity = new class {
            private string $firstName;
            private string $lastName;

            public function getFirstName(): string
            {
                return $this->firstName;
            }

            public function getLastName(): string
            {
                return $this->lastName;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'first_name', 'John');
        $this->setPropertyValue->__invoke($entity, 'last_name', 'Doe');

        $this->assertEquals('John', $entity->getFirstName());
        $this->assertEquals('Doe', $entity->getLastName());
    }

    public function testHandlesNullValue(): void
    {
        $entity = new class {
            private ?string $name = 'default';

            public function getName(): ?string
            {
                return $this->name;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'name', null);

        $this->assertNull($entity->getName());
    }

    public function testHandlesArrayValue(): void
    {
        $entity = new class {
            private array $tags;

            public function getTags(): array
            {
                return $this->tags;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'tags', ['php', 'testing']);

        $this->assertEquals(['php', 'testing'], $entity->getTags());
    }

    public function testHandlesDateTimeValue(): void
    {
        $entity = new class {
            private DateTime $createdAt;

            public function getCreatedAt(): DateTime
            {
                return $this->createdAt;
            }
        };

        $dateTime = new DateTime('2024-01-15 14:30:45');
        $this->setPropertyValue->__invoke($entity, 'created_at', $dateTime);

        $this->assertInstanceOf(DateTime::class, $entity->getCreatedAt());
        $this->assertEquals('2024-01-15 14:30:45', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testHandlesBooleanValue(): void
    {
        $entity = new class {
            private bool $isActive;

            public function isActive(): bool
            {
                return $this->isActive;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'is_active', true);

        $this->assertTrue($entity->isActive());
    }

    public function testHandlesIntValue(): void
    {
        $entity = new class {
            private int $age;

            public function getAge(): int
            {
                return $this->age;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'age', 30);

        $this->assertEquals(30, $entity->getAge());
    }

    public function testIgnoresUnknownProperty(): void
    {
        $entity = new class {
            private string $name = 'default';

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'unknown_field', 'value');

        // Should not throw exception, property remains unchanged
        $this->assertEquals('default', $entity->getName());
    }

    public function testTypeCastsStringToInt(): void
    {
        $entity = new class {
            private int $age;

            public function getAge(): int
            {
                return $this->age;
            }
        };

        $this->setPropertyValueWithCaster->__invoke($entity, 'age', '30');

        $this->assertEquals(30, $entity->getAge());
        $this->assertIsInt($entity->getAge());
    }

    public function testTypeCastsStringToBool(): void
    {
        $entity = new class {
            private bool $isActive;

            public function isActive(): bool
            {
                return $this->isActive;
            }
        };

        $this->setPropertyValueWithCaster->__invoke($entity, 'is_active', '1');

        $this->assertTrue($entity->isActive());
        $this->assertIsBool($entity->isActive());
    }

    public function testTypeCastsStringToDateTime(): void
    {
        $entity = new class {
            private DateTime $createdAt;

            public function getCreatedAt(): DateTime
            {
                return $this->createdAt;
            }
        };

        $this->setPropertyValueWithCaster->__invoke($entity, 'created_at', '2024-01-15 14:30:45');

        $this->assertInstanceOf(DateTime::class, $entity->getCreatedAt());
        $this->assertEquals('2024-01-15 14:30:45', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testCachesReflectionProperty(): void
    {
        $entity = new class {
            private string $name;

            public function getName(): string
            {
                return $this->name;
            }
        };

        // Multiple calls should use cached ReflectionProperty
        $this->setPropertyValue->__invoke($entity, 'name', 'First');
        $this->setPropertyValue->__invoke($entity, 'name', 'Second');
        $this->setPropertyValue->__invoke($entity, 'name', 'Third');

        $this->assertEquals('Third', $entity->getName());
    }

    public function testPrefersSetterOverDirectAccess(): void
    {
        $entity = new class {
            private string $name;
            public bool $setterCalled = false;

            public function setName(string $name): void
            {
                $this->setterCalled = true;
                $this->name = $name;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->setPropertyValue->__invoke($entity, 'name', 'John');

        $this->assertTrue($entity->setterCalled);
        $this->assertEquals('John', $entity->getName());
    }
}
