<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Fields
{
    public function __construct(
        public string $parameter = 'fields',
        public string $separator = ',',
    ) {}
}
