<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Generates a UUID v4 (random) according to RFC 4122.
 *
 * Uses cryptographically secure random bytes with version 4
 * and variant bits set per specification.
 * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 */
class GenerateUuid4
{
    /**
     * Generates and returns a new UUID v4 string.
     *
     * @return string UUID v4 in canonical format
     */
    public function __invoke(): string
    {
        $bytes = random_bytes(16);

        // Set version to 4 (0100 in high nibble of byte 6)
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);

        // Set variant to RFC 4122 (10xx in high bits of byte 8)
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }
}
