<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\PagePagination;
use RemoteModels\RemoteModel;

#[Endpoint('/commits')]
#[PagePagination]
class Commit extends RemoteModel {}
