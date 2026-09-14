<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Helpdesk;

use RemoteModels\RemoteModel;

class Article extends RemoteModel
{
    protected $connection = 'helpdesk';

    protected ?int $cacheFor = 60;
}
