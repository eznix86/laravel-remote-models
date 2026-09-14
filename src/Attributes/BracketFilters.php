<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class BracketFilters extends Filters
{
    public function __construct(
        public string $prefix = '',
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function compare(array $parameters, string $column, string $operator, string $value): array
    {
        $parameters[$column.'['.$this->prefix.$operator.']'] = $value;

        return $parameters;
    }
}
