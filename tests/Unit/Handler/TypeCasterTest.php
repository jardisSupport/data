<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Handler;

use DateTime;
use JardisSupport\Data\Handler\TypeCaster;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class TypeCasterTest extends TestCase
{
    private TypeCaster $typeCaster;

    protected function setUp(): void
    {
        $this->typeCaster = new TypeCaster();
    }

    public function testReturnsNullForNullValue(): void
    {
        $entity = new class {
            private ?string $name = null;
        };
        $property = new ReflectionProperty($entity, 'name');

        $result = $this->typeCaster->__invoke(null, $property);

        $this->assertNull($result);
    }

    public function testCastsStringToInt(): void
    {
        $entity = new class {
            private int $age = 0;
        };
        $property = new ReflectionProperty($entity, 'age');

        $result = $this->typeCaster->__invoke('42', $property);

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function testCastsStringToBool(): void
    {
        $entity = new class {
            private bool $isActive = false;
        };
        $property = new ReflectionProperty($entity, 'isActive');

        $result = $this->typeCaster->__invoke('1', $property);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCastsBoolFalse(): void
    {
        $entity = new class {
            private bool $isActive = false;
        };
        $property = new ReflectionProperty($entity, 'isActive');

        $result = $this->typeCaster->__invoke('0', $property);

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testCastsStringToFloat(): void
    {
        $entity = new class {
            private float $price = 0.0;
        };
        $property = new ReflectionProperty($entity, 'price');

        $result = $this->typeCaster->__invoke('19.99', $property);

        $this->assertIsFloat($result);
        $this->assertEquals(19.99, $result);
    }

    public function testCastsToString(): void
    {
        $entity = new class {
            private string $description = '';
        };
        $property = new ReflectionProperty($entity, 'description');

        $result = $this->typeCaster->__invoke(123, $property);

        $this->assertIsString($result);
        $this->assertEquals('123', $result);
    }

    public function testParsesDateTimeFromFullFormat(): void
    {
        $entity = new class {
            private DateTime $createdAt;
        };
        $property = new ReflectionProperty($entity, 'createdAt');

        $result = $this->typeCaster->__invoke('2024-01-15 14:30:45', $property);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-01-15 14:30:45', $result->format('Y-m-d H:i:s'));
    }

    public function testParsesDateTimeFromDateFormat(): void
    {
        $entity = new class {
            private DateTime $birthDate;
        };
        $property = new ReflectionProperty($entity, 'birthDate');

        $result = $this->typeCaster->__invoke('2024-01-15', $property);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
    }

    public function testParsesDateTimeFromTimeFormat(): void
    {
        $entity = new class {
            private DateTime $time;
        };
        $property = new ReflectionProperty($entity, 'time');

        $result = $this->typeCaster->__invoke('14:30:45', $property);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('14:30:45', $result->format('H:i:s'));
    }

    public function testReturnsDateTimeObjectAsIs(): void
    {
        $entity = new class {
            private DateTime $createdAt;
        };
        $property = new ReflectionProperty($entity, 'createdAt');

        $dateTime = new DateTime('2024-01-15 14:30:45');
        $result = $this->typeCaster->__invoke($dateTime, $property);

        $this->assertSame($dateTime, $result);
    }

    public function testReturnsNullForInvalidDateTimeString(): void
    {
        $entity = new class {
            private DateTime $createdAt;
        };
        $property = new ReflectionProperty($entity, 'createdAt');

        $result = $this->typeCaster->__invoke('invalid-date', $property);

        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyDateTimeString(): void
    {
        $entity = new class {
            private DateTime $createdAt;
        };
        $property = new ReflectionProperty($entity, 'createdAt');

        $result = $this->typeCaster->__invoke('', $property);

        $this->assertNull($result);
    }

    public function testReturnsValueAsIsWhenNoTypeHint(): void
    {
        $entity = new class {
            private $mixed = null;
        };
        $property = new ReflectionProperty($entity, 'mixed');

        $result = $this->typeCaster->__invoke('some value', $property);

        $this->assertEquals('some value', $result);
    }

    public function testCachesTypeNames(): void
    {
        $entity = new class {
            private int $counter = 0;
        };
        $property = new ReflectionProperty($entity, 'counter');

        // First call - populates cache
        $result1 = $this->typeCaster->__invoke('42', $property);
        $this->assertEquals(42, $result1);

        // Second call - uses cached type
        $result2 = $this->typeCaster->__invoke('99', $property);
        $this->assertEquals(99, $result2);
    }

    public function testHandlesFloatAsDouble(): void
    {
        $entity = new class {
            private float $value = 0.0;
        };
        $property = new ReflectionProperty($entity, 'value');

        $result = $this->typeCaster->__invoke('3.14159', $property);

        $this->assertIsFloat($result);
        $this->assertEquals(3.14159, $result);
    }

    public function testHandlesIntegerTypeAlias(): void
    {
        $entity = new class {
            private int $count = 0;
        };
        $property = new ReflectionProperty($entity, 'count');

        $result = $this->typeCaster->__invoke('100', $property);

        $this->assertIsInt($result);
        $this->assertEquals(100, $result);
    }

    public function testReturnsValueForUnknownType(): void
    {
        $entity = new class {
            private array $items = [];
        };
        $property = new ReflectionProperty($entity, 'items');

        $inputArray = [1, 2, 3];
        $result = $this->typeCaster->__invoke($inputArray, $property);

        $this->assertEquals($inputArray, $result);
    }
}
