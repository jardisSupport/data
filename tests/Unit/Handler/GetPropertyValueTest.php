<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use JardisSupport\Data\Handler\ColumnNameToPropertyName;
use JardisSupport\Data\Handler\GetPropertyValue;
use PHPUnit\Framework\TestCase;

class GetPropertyValueTest extends TestCase
{
    private GetPropertyValue $getPropertyValue;

    protected function setUp(): void
    {
        $this->getPropertyValue = new GetPropertyValue(new ColumnNameToPropertyName());
    }

    public function testGetsValueViaGetterMethod(): void
    {
        $entity = new class {
            private string $name = 'John Doe';

            public function getName(): string
            {
                return $this->name;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'name');

        $this->assertEquals('John Doe', $result);
    }

    public function testGetsValueViaReflectionWhenNoGetter(): void
    {
        $entity = new class {
            private string $email = 'john@example.com';
        };

        $result = $this->getPropertyValue->__invoke($entity, 'email');

        $this->assertEquals('john@example.com', $result);
    }

    public function testHandlesSnakeCaseColumnName(): void
    {
        $entity = new class {
            private string $firstName = 'John';

            public function getFirstName(): string
            {
                return $this->firstName;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'first_name');

        $this->assertEquals('John', $result);
    }

    public function testReturnsNullForNonExistentProperty(): void
    {
        $entity = new class {
            private string $name = 'John';
        };

        $result = $this->getPropertyValue->__invoke($entity, 'non_existent');

        $this->assertNull($result);
    }

    public function testHandlesRepeatedCallsToSameProperty(): void
    {
        $entity = new class {
            private int $counter = 0;

            public function incrementCounter(): void
            {
                $this->counter++;
            }
        };

        // First call
        $result1 = $this->getPropertyValue->__invoke($entity, 'counter');
        $this->assertEquals(0, $result1);

        // Modify value
        $entity->incrementCounter();

        // Second call - uses cached reflection
        $result2 = $this->getPropertyValue->__invoke($entity, 'counter');
        $this->assertEquals(1, $result2);
    }

    public function testHandlesProtectedProperties(): void
    {
        $entity = new class {
            protected string $status = 'active';
        };

        $result = $this->getPropertyValue->__invoke($entity, 'status');

        $this->assertEquals('active', $result);
    }

    public function testHandlesPublicProperties(): void
    {
        $entity = new class {
            public string $publicProp = 'public value';
        };

        $result = $this->getPropertyValue->__invoke($entity, 'publicProp');

        $this->assertEquals('public value', $result);
    }

    public function testPrefersGetterOverDirectAccess(): void
    {
        $entity = new class {
            private string $name = 'John';

            public function getName(): string
            {
                return strtoupper($this->name);
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'name');

        // Should return the getter's result (uppercase), not direct property value
        $this->assertEquals('JOHN', $result);
    }

    public function testHandlesNullValues(): void
    {
        $entity = new class {
            private ?string $optionalField = null;

            public function getOptionalField(): ?string
            {
                return $this->optionalField;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'optional_field');

        $this->assertNull($result);
    }

    public function testHandlesBooleanValues(): void
    {
        $entity = new class {
            private bool $isActive = true;

            public function getIsActive(): bool
            {
                return $this->isActive;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'is_active');

        $this->assertTrue($result);
    }

    public function testHandlesArrayValues(): void
    {
        $entity = new class {
            private array $tags = ['php', 'testing'];

            public function getTags(): array
            {
                return $this->tags;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'tags');

        $this->assertEquals(['php', 'testing'], $result);
    }

    public function testHandlesObjectValues(): void
    {
        $address = new class {
            public string $city = 'Berlin';
        };

        $entity = new class ($address) {
            private object $address;

            public function __construct(object $address)
            {
                $this->address = $address;
            }

            public function getAddress(): object
            {
                return $this->address;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'address');

        $this->assertIsObject($result);
        $this->assertEquals('Berlin', $result->city);
    }

    public function testGetsValueViaIsGetter(): void
    {
        $entity = new class {
            private bool $active = true;

            public function isActive(): bool
            {
                return $this->active;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'active');

        $this->assertTrue($result);
    }

    public function testGetsValueViaHasGetter(): void
    {
        $entity = new class {
            private bool $permissions = true;

            public function hasPermissions(): bool
            {
                return $this->permissions;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'permissions');

        $this->assertTrue($result);
    }

    public function testPrefersGetOverIsGetter(): void
    {
        $entity = new class {
            private bool $active = true;

            public function getActive(): bool
            {
                return false; // Different from isActive
            }

            public function isActive(): bool
            {
                return $this->active;
            }
        };

        $result = $this->getPropertyValue->__invoke($entity, 'active');

        // get*() has priority over is*()
        $this->assertFalse($result);
    }

    public function testReturnsNullConsistentlyForNonExistentProperty(): void
    {
        $entity = new class {
            private string $name = 'John';
        };

        $result1 = $this->getPropertyValue->__invoke($entity, 'non_existent');
        $this->assertNull($result1);

        // Repeated calls also return null
        $result2 = $this->getPropertyValue->__invoke($entity, 'non_existent');
        $this->assertNull($result2);
    }
}
