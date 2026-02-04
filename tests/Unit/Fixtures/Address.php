<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

/**
 * Fixture: Simple value object for testing nested object hydration
 */
class Address
{
    private string $street;
    private string $city;
    private string $zipCode;
    private ?Country $country = null;

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }
}
