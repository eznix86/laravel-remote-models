<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Helpdesk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RemoteModels\RemoteModel;

class Ticket extends RemoteModel
{
    protected $connection = 'helpdesk';

    protected $fillable = ['subject'];

    /**
     * @param  array<string, mixed>  $query
     */
    protected function index(PendingRequest $http, array $query): Response
    {
        return $http->get('/v2/tickets', $query);
    }

    protected function show(PendingRequest $http, string|int $id): Response
    {
        return $http->get("/v2/tickets/{$id}");
    }
}
