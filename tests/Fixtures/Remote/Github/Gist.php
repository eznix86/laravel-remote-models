<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteBuilder;
use RemoteModels\RemoteModel;

#[Connection('github-enterprise')]
#[Fillable(['title'])]
#[Endpoint('/gists', key: 'slug', keyType: 'string')]
class Gist extends RemoteModel
{
    /**
     * @param  RemoteBuilder<self>  $query
     * @return RemoteBuilder<self>
     */
    #[Scope]
    protected function starred(RemoteBuilder $query): RemoteBuilder
    {
        return $query->where('starred', 'yes');
    }
}
