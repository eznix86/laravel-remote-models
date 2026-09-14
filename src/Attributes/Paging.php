<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Paging
{
    public function __construct(
        public string $perPage = 'per_page',
        public ?string $page = 'page',
    ) {}
}
