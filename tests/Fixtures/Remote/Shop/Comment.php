<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Shop;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Fields;
use RemoteModels\Attributes\SuffixFilters;
use RemoteModels\RemoteModel;

#[Endpoint('/comments')]
#[SuffixFilters]
#[Fields]
class Comment extends RemoteModel
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }
}
