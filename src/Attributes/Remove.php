<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Remove extends Route
{
    public function __construct(string $uri, string $method = 'delete')
    {
        parent::__construct($uri, $method);
    }
}
