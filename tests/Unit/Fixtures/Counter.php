<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

use JardisSupport\Data\Attribute\Aggregate;

/**
 * Fixture: Aggregate root with relations for testing relation-aware behavior
 *
 * Mirrors real builder output with typed setters for relation properties.
 */
#[Aggregate(name: 'Counter', root: true)]
class Counter
{
    private ?int $id = null;
    private ?string $identifier = null;
    private ?string $counterNumber = null;
    private ?string $clientIdentifier = null;
    private ?\DateTimeImmutable $activeFrom = null;
    private ?\DateTimeImmutable $activeUntil = null;

    /** @var CounterRegister[] */
    private array $counterRegister = [];

    private ?CounterGateway $counterGateway = null;

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

    public function getCounterNumber(): ?string
    {
        return $this->counterNumber;
    }

    public function setCounterNumber(string $counterNumber): void
    {
        $this->counterNumber = $counterNumber;
    }

    public function getClientIdentifier(): ?string
    {
        return $this->clientIdentifier;
    }

    public function setClientIdentifier(string $clientIdentifier): void
    {
        $this->clientIdentifier = $clientIdentifier;
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

    /**
     * @return CounterRegister[]
     */
    public function getCounterRegister(): array
    {
        return $this->counterRegister;
    }

    public function addCounterRegister(CounterRegister $counterRegister): self
    {
        $this->counterRegister[] = $counterRegister;
        return $this;
    }

    public function getCounterGateway(): ?CounterGateway
    {
        return $this->counterGateway;
    }

    public function setCounterGateway(?CounterGateway $counterGateway): self
    {
        $this->counterGateway = $counterGateway;
        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->__snapshot;
    }
}
