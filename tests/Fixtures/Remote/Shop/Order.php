<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Shop;

use RemoteModels\Attributes\BracketFilters;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteModel;

#[Endpoint('/orders')]
#[BracketFilters]
class Order extends RemoteModel {}
