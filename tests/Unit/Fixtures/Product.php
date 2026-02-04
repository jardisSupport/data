<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

use DateTime;

/**
 * Fixture: Entity with setter methods for testing setter-based hydration
 */
class Product
{
    private string $id = '';
    private string $name = '';
    private float $price = 0.0;
    private bool $isActive = false;
    private ?DateTime $createdAt = null;
    private int $stock = 0;

    // Track if setters were called
    private bool $settersCalled = false;

    public function setId(string $id): void
    {
        $this->id = strtoupper($id);
        $this->settersCalled = true;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
        $this->settersCalled = true;
    }

    public function setPrice(float $price): void
    {
        $this->price = round($price, 2);
        $this->settersCalled = true;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->settersCalled = true;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
        $this->settersCalled = true;
    }

    public function setStock(int $stock): void
    {
        $this->stock = max(0, $stock); // Never negative
        $this->settersCalled = true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function wereSettersCalled(): bool
    {
        return $this->settersCalled;
    }
}
