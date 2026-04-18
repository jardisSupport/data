<?php

declare(strict_types=1);

namespace JardisSupport\Data\Tests\Unit\Fixtures;

/**
 * Fixture: BackedEnum for Gateway type
 */
enum GatewayType: string
{
    case Electricity = 'ELECTRICITY';
    case Gas = 'GAS';
}
