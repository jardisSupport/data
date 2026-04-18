<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

use JardisSupport\Data\Attribute\Aggregate;

/**
 * Fixture: Many-grandchild leaf entity (Counter → CounterGateway → CounterGatewayRegister)
 */
#[Aggregate(name: 'CounterGatewayRegister')]
class CounterGatewayRegister
{
    private ?int $id = null;
    private ?int $counterGatewayId = null;
    private ?int $registerId = null;
    private ?string $configurationIdentification = null;
    private array $__snapshot = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCounterGatewayId(): ?int
    {
        return $this->counterGatewayId;
    }

    public function setCounterGatewayId(int $counterGatewayId): void
    {
        $this->counterGatewayId = $counterGatewayId;
    }

    public function getRegisterId(): ?int
    {
        return $this->registerId;
    }

    public function setRegisterId(int $registerId): void
    {
        $this->registerId = $registerId;
    }

    public function getConfigurationIdentification(): ?string
    {
        return $this->configurationIdentification;
    }

    public function setConfigurationIdentification(string $configurationIdentification): void
    {
        $this->configurationIdentification = $configurationIdentification;
    }

    public function getSnapshot(): array
    {
        return $this->__snapshot;
    }
}
