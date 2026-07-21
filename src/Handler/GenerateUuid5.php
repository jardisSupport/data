<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Generates a UUID v5 (name-based, SHA-1) according to RFC 4122.
 *
 * Deterministic: the same namespace + name always produces the same UUID.
 * Useful for deriving stable identities from business data.
 * Format: xxxxxxxx-xxxx-5xxx-yxxx-xxxxxxxxxxxx
 */
class GenerateUuid5
{
    /**
     * Generates and returns a new UUID v5 string.
     *
     * @param string $namespace A valid UUID used as namespace
     * @param string $name The name to hash within the namespace
     * @return string UUID v5 in canonical format
     */
    public function __invoke(string $namespace, string $name): string
    {
        // Convert namespace UUID to binary (remove dashes)
        $namespaceBytes = hex2bin(str_replace('-', '', $namespace));

        // SHA-1 hash of namespace + name
        $hash = sha1($namespaceBytes . $name);

        // Set version to 5 (0101 in high nibble of byte 6)
        $byte6 = (hexdec(substr($hash, 12, 2)) & 0x0F) | 0x50;

        // Set variant to RFC 4122 (10xx in high bits of byte 8)
        $byte8 = (hexdec(substr($hash, 16, 2)) & 0x3F) | 0x80;

        return sprintf(
            '%s-%s-%02x%s-%02x%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            $byte6,
            substr($hash, 14, 2),
            $byte8,
            substr($hash, 18, 2),
            substr($hash, 20, 12)
        );
    }
}
