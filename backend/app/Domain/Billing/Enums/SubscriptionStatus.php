<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
}
