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

    public function setId(string $id): void
    {
        $this->id = strtoupper($id);
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function setPrice(float $price): void
    {
        $this->price = round($price, 2);
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setStock(int $stock): void
    {
        $this->stock = max(0, $stock); // Never negative
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

}
