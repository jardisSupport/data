<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

use JardisSupport\Data\Attribute\Aggregate;

/**
 * Fixture: Grandchild leaf entity (Counter → CounterGateway → Gateway)
 */
#[Aggregate(name: 'Gateway')]
class Gateway
{
    private ?int $id = null;
    private ?string $identifier = null;
    private ?GatewayType $type = null;
    private array $__snapshot = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getType(): ?GatewayType
    {
        return $this->type;
    }

    public function setType(GatewayType $type): void
    {
        $this->type = $type;
    }

    public function getSnapshot(): array
    {
        return $this->__snapshot;
    }
}
