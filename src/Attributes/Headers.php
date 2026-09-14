<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Headers
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public array $headers,
    ) {}
}
