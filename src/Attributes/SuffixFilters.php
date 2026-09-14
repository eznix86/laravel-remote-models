<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class SuffixFilters extends Filters
{
    public function __construct(
        public string $separator = '__',
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function compare(array $parameters, string $column, string $operator, string $value): array
    {
        $parameters[$column.$this->separator.$operator] = $value;

        return $parameters;
    }
}
