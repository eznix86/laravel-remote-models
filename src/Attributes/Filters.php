<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

abstract class Filters
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    abstract public function compare(array $parameters, string $column, string $operator, string $value): array;
}
