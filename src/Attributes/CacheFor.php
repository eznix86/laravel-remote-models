<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class CacheFor
{
    public function __construct(
        public int $seconds,
        public ?string $store = null,
    ) {}
}
