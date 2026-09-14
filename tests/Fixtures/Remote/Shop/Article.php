<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Shop;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\SuffixFilters;
use RemoteModels\Relations\RemoteHasMany;
use RemoteModels\RemoteModel;

#[Endpoint('/articles')]
#[SuffixFilters]
class Article extends RemoteModel
{
    public function comments(): RemoteHasMany
    {
        return $this->remoteHasMany(Comment::class)->via('/articles/{key}/comments');
    }
}
