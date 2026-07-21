<?php

declare(strict_types=1);

namespace JardisSupport\Data\Handler;

use BackedEnum;
use DateTimeInterface;

/**
 * Converts a typed property value to its scalar snapshot representation.
 *
 * Snapshot values are always scalar: DateTime becomes 'Y-m-d H:i:s' string,
 * BackedEnum becomes its backing value, everything else passes through.
 */
class ToSnapshotValue
{
    /**
     * @param mixed $value Typed property value
     * @return mixed Scalar snapshot representation
     */
    public function __invoke(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}
