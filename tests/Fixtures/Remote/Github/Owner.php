<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteModel;

#[Endpoint('/users', key: 'login', keyType: 'string')]
class Owner extends RemoteModel {}
