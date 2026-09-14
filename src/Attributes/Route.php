<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

abstract class Route
{
    public function __construct(
        public string $uri,
        public string $method,
    ) {}
}
