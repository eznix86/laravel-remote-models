<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use RemoteModels\Attributes\CacheFor;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteModel;

#[Endpoint('/tags', key: 'name', keyType: 'string')]
#[CacheFor(300)]
class Tag extends RemoteModel
{
    protected $fillable = ['name'];
}
