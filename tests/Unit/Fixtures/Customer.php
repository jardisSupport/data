<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

/**
 * Fixture: Aggregate root for testing complex nested hydration
 */
class Customer
{
    private string $id;
    private string $name;
    private string $email;
    private ?Address $billingAddress = null;
    private ?Address $shippingAddress = null;

    /**
     * @var OrderItem[]
     */
    private array $recentOrders = [];

    /**
     * @var string[]
     */
    private array $tags = [];

    private array $metadata = [];
    private array $__snapshot = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    /**
     * @return OrderItem[]
     */
    public function getRecentOrders(): array
    {
        return $this->recentOrders;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getSnapshot(): array
    {
        return $this->__snapshot;
    }
}
