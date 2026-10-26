<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';
}
