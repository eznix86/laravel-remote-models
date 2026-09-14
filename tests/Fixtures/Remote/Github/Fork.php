<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteModel;
use RemoteModels\Tests\Fixtures\PlainBuilder;

#[Endpoint('/forks')]
#[UseEloquentBuilder(PlainBuilder::class)]
class Fork extends RemoteModel {}
