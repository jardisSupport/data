<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Generates a NanoID — a compact, URL-safe random identifier.
 *
 * Uses cryptographically secure random bytes with a configurable alphabet.
 * Default: 21 characters from A-Za-z0-9_- (~126 bits of entropy).
 */
class GenerateNanoId
{
    private const DEFAULT_ALPHABET = '_-0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const DEFAULT_LENGTH = 21;

    /**
     * Generates and returns a new NanoID string.
     *
     * @param int $length Length of the generated ID
     * @param string $alphabet Characters to use for generation
     * @return string NanoID string
     */
    public function __invoke(int $length = self::DEFAULT_LENGTH, string $alphabet = self::DEFAULT_ALPHABET): string
    {
        $alphabetLength = strlen($alphabet);

        // Bitmask to reduce bias: largest power-of-2 mask that covers alphabetLength
        $mask = (1 << (int) ceil(log($alphabetLength, 2))) - 1;

        $id = '';
        while (strlen($id) < $length) {
            $bytes = random_bytes($length);
            for ($i = 0; $i < $length && strlen($id) < $length; $i++) {
                $index = ord($bytes[$i]) & $mask;
                if ($index < $alphabetLength) {
                    $id .= $alphabet[$index];
                }
            }
        }

        return $id;
    }
}
