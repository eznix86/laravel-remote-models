<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentFailed = 'payment_failed';
}
