<?php

declare(strict_types=1);

namespace JardisSupport\Data;

use JardisSupport\Data\Handler\GenerateNanoId;
use JardisSupport\Data\Handler\GenerateUuid4;
use JardisSupport\Data\Handler\GenerateUuid5;
use JardisSupport\Data\Handler\GenerateUuid7;
use JardisSupport\Contract\Data\IdentityInterface;

/**
 * Identity generation service with support for UUID v4, v5, v7 and NanoID.
 */
class Identity implements IdentityInterface
{
    private ?GenerateUuid4 $generateUuid4 = null;
    private ?GenerateUuid5 $generateUuid5 = null;
    private ?GenerateUuid7 $generateUuid7 = null;
    private ?GenerateNanoId $generateNanoId = null;

    /**
     * Generates a UUID v4 (random).
     *
     * @return string UUID v4 in canonical format
     */
    public function generateUuid4(): string
    {
        return ($this->getGenerateUuid4())();
    }

    /**
     * Generates a UUID v7 (time-ordered).
     *
     * @return string UUID v7 in canonical format
     */
    public function generateUuid7(): string
    {
        return ($this->getGenerateUuid7())();
    }

    /**
     * Generates a UUID v5 (name-based, deterministic).
     *
     * @param string $namespace A valid UUID used as namespace
     * @param string $name The name to hash within the namespace
     * @return string UUID v5 in canonical format
     */
    public function generateUuid5(string $namespace, string $name): string
    {
        return ($this->getGenerateUuid5())($namespace, $name);
    }

    /**
     * Generates a NanoID — a compact, URL-safe random identifier.
     *
     * @param int $length Length of the generated ID (default: 21)
     * @param string $alphabet Characters to use (default: A-Za-z0-9_-)
     * @return string NanoID string
     */
    public function generateNanoId(
        int $length = 21,
        string $alphabet = '_-0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ): string {
        return ($this->getGenerateNanoId())($length, $alphabet);
    }

    private function getGenerateUuid4(): GenerateUuid4
    {
        return $this->generateUuid4 ??= new GenerateUuid4();
    }

    private function getGenerateUuid5(): GenerateUuid5
    {
        return $this->generateUuid5 ??= new GenerateUuid5();
    }

    private function getGenerateUuid7(): GenerateUuid7
    {
        return $this->generateUuid7 ??= new GenerateUuid7();
    }

    private function getGenerateNanoId(): GenerateNanoId
    {
        return $this->generateNanoId ??= new GenerateNanoId();
    }
}
