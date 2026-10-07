<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * Projects a PHP payload onto its wire (JSON response) representation.
 *
 * Counterpart to TypeCaster (DB → PHP): this handler goes PHP → wire.
 * A type map names, per payload key, the wire format ('date', 'date-time',
 * 'time') or, for a child, a nested map. Keys not in the map pass through.
 *
 * All wall-clock values are UTC by convention: a DateTimeInterface is NOT
 * converted, its wall clock is read as UTC; strings without offset are read
 * as UTC, strings with an offset are converted to UTC. Zero-dates, empty and
 * unparsable strings become null. Sub-second precision is dropped.
 *
 * Stateless — no internal caches.
 */
class ProjectWire
{
    /**
     * @param array<array-key, mixed> $payload
     * @param array<string, string|array<string, mixed>> $typeMap field name → wire format or nested map
     * @return array<array-key, mixed>
     */
    public function __invoke(array $payload, array $typeMap): array
    {
        foreach ($payload as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $typeMap)) {
                continue;
            }

            $payload[$key] = $this->project($value, $typeMap[$key]);
        }

        return $payload;
    }

    /**
     * @param mixed $value
     * @param string|array<string, mixed> $spec
     */
    private function project(mixed $value, string|array $spec): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($spec)) {
            if (!is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map(
                    fn(mixed $item): mixed => is_array($item) ? $this->__invoke($item, $spec) : $item,
                    $value
                );
            }
            return $this->__invoke($value, $spec);
        }

        if (!is_string($value) && !$value instanceof DateTimeInterface) {
            return $value;
        }

        return match ($spec) {
            'date-time' => $this->dateTime($value),
            'date' => $this->date($value),
            'time' => $this->time($value),
            default => $value,
        };
    }

    private function dateTime(string|DateTimeInterface $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s') . '+00:00';
        }

        $parsed = $this->parse($value);

        return $parsed === null
            ? null
            : $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s') . '+00:00';
    }

    private function date(string|DateTimeInterface $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:$|[ T])/', $value, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function time(string|DateTimeInterface $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (preg_match('/^(\d{2}:\d{2}:\d{2})(?:\.\d+)?$/', trim($value), $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function parse(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }
    }
}
