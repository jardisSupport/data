<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

/**
 * Fixture: Item for testing array of objects hydration
 */
class OrderItem
{
    private string $productName;
    private int $quantity;
    private float $price;

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
