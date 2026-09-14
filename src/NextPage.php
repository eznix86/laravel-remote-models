<?php

declare(strict_types=1);

namespace RemoteModels;

final class NextPage
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        public ?string $uri = null,
        public array $query = [],
    ) {}
}
