<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

use JardisSupport\Data\Attribute\Aggregate;

/**
 * Fixture: Child entity for Counter aggregate (one relation)
 *
 * Mirrors real builder output: has own relation properties with typed setters.
 * Counter → CounterGateway → Gateway (3-level nesting)
 */
#[Aggregate(name: 'CounterGateway')]
class CounterGateway
{
    private ?int $id = null;
    private ?int $counterId = null;
    private ?\DateTimeImmutable $activeFrom = null;
    private ?\DateTimeImmutable $activeUntil = null;

    private ?Gateway $gateway = null;

    /** @var CounterGatewayRegister[] */
    private array $counterGatewayRegister = [];

    private array $__snapshot = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCounterId(): ?int
    {
        return $this->counterId;
    }

    public function setCounterId(int $counterId): void
    {
        $this->counterId = $counterId;
    }

    public function getActiveFrom(): ?\DateTimeImmutable
    {
        return $this->activeFrom;
    }

    public function setActiveFrom(\DateTimeImmutable $activeFrom): void
    {
        $this->activeFrom = $activeFrom;
    }

    public function getActiveUntil(): ?\DateTimeImmutable
    {
        return $this->activeUntil;
    }

    public function setActiveUntil(?\DateTimeImmutable $activeUntil): void
    {
        $this->activeUntil = $activeUntil;
    }

    public function getGateway(): ?Gateway
    {
        return $this->gateway;
    }

    public function setGateway(?Gateway $gateway): self
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @return CounterGatewayRegister[]
     */
    public function getCounterGatewayRegister(): array
    {
        return $this->counterGatewayRegister;
    }

    public function addCounterGatewayRegister(CounterGatewayRegister $counterGatewayRegister): self
    {
        $this->counterGatewayRegister[] = $counterGatewayRegister;
        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->__snapshot;
    }
}
