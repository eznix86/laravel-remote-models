<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Shop;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\SuffixFilters;
use RemoteModels\RemoteModel;

#[Endpoint('/invoices')]
#[SuffixFilters]
class Invoice extends RemoteModel {}
