<?php

declare(strict_types=1);

namespace App\Domain\Store\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
