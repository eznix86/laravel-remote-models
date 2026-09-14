<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Endpoint
{
    public function __construct(
        public string $path,
        public ?string $key = null,
        public ?string $keyType = null,
    ) {}
}
