<?php

declare(strict_types=1);

namespace RemoteModels;

use Illuminate\Database\Connection;
use RemoteModels\Exceptions\UnsupportedQuery;

class RemoteConnection extends Connection
{
    public function __construct(string $name)
    {
        parent::__construct(
            static fn (): never => throw UnsupportedQuery::database(),
            $name,
            '',
            ['name' => $name, 'driver' => 'remote'],
        );
    }
}
