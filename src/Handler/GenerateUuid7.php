<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

/**
 * Generates a UUID v7 (time-ordered) according to RFC 9562.
 *
 * Uses Unix timestamp in milliseconds (48 bits) combined with a monotonic
 * counter (12 bits) and random data (62 bits) plus version and variant bits.
 * The counter ensures strict ordering when multiple UUIDs are generated
 * within the same millisecond (e.g. batch inserts of aggregate children).
 * UUIDs are lexicographically sortable by creation time.
 * Format: xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
 */
class GenerateUuid7
{
    private int $lastTimestamp = 0;
    private int $counter = 0;

    /**
     * Generates and returns a new UUID v7 string.
     *
     * @return string UUID v7 in canonical format
     */
    public function __invoke(): string
    {
        // 48-bit Unix timestamp in milliseconds
        $timestamp = (int) (microtime(true) * 1000);

        if ($timestamp === $this->lastTimestamp) {
            // Same millisecond: increment counter
            $this->counter++;
            if ($this->counter > 0xFFF) {
                // Counter overflow: wait for next millisecond
                while ($timestamp === $this->lastTimestamp) {
                    $timestamp = (int) (microtime(true) * 1000);
                }
                $this->counter = random_int(0, 0x1F);
            }
        } else {
            // New millisecond: randomize counter start to avoid collisions across instances
            $this->counter = random_int(0, 0x1F);
        }
        $this->lastTimestamp = $timestamp;

        // 8 random bytes for rand_b (bytes 8-15)
        $random = random_bytes(8);

        // Build the 16-byte UUID
        // Bytes 0-5: 48-bit timestamp (big-endian)
        $bytes = pack('J', $timestamp);
        // pack('J') gives 8 bytes, we need the last 6 (48 bits)
        $bytes = substr($bytes, 2, 6);

        // Bytes 6-7: 4-bit version (0111) + 12-bit monotonic counter
        $byte6 = (($this->counter >> 8) & 0x0F) | 0x70; // version 7 + counter high 4 bits
        $byte7 = $this->counter & 0xFF; // counter low 8 bits
        $bytes .= chr($byte6) . chr($byte7);

        // Bytes 8-15: 2-bit variant (10) + 62-bit rand_b
        $byte8 = (ord($random[0]) & 0x3F) | 0x80; // variant RFC 4122
        $bytes .= chr($byte8);
        $bytes .= substr($random, 1, 7);

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
