<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Illuminate\Http\Client\Response;
use RemoteModels\NextPage;

abstract class Pagination
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, array<string, mixed>>  $records
     */
    abstract public function next(Response $response, array $query, array $records): ?NextPage;
}
