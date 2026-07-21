<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

use JardisSupport\Data\Attribute\Aggregate;

/**
 * Fixture: Child entity for Counter aggregate (many relation)
 */
#[Aggregate(name: 'CounterRegister')]
class CounterRegister
{
    private ?int $id = null;
    private ?int $counterId = null;
    private ?int $registerId = null;
    private ?string $relatedType = null;
    private ?\DateTimeImmutable $activeFrom = null;
    private ?\DateTimeImmutable $activeUntil = null;
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

    public function getRegisterId(): ?int
    {
        return $this->registerId;
    }

    public function setRegisterId(int $registerId): void
    {
        $this->registerId = $registerId;
    }

    public function getRelatedType(): ?string
    {
        return $this->relatedType;
    }

    public function setRelatedType(string $relatedType): void
    {
        $this->relatedType = $relatedType;
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

    public function getSnapshot(): array
    {
        return $this->__snapshot;
    }
}
