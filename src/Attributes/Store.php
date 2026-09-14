<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Store extends Route
{
    public function __construct(string $uri, string $method = 'post')
    {
        parent::__construct($uri, $method);
    }
}
