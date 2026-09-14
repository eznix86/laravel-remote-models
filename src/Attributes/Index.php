<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Index extends Route
{
    public function __construct(string $uri, string $method = 'get')
    {
        parent::__construct($uri, $method);
    }
}
