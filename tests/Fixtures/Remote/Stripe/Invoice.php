<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Stripe;

use Illuminate\Http\Client\Response;
use RemoteModels\Attributes\BracketFilters;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Paging;
use RemoteModels\Attributes\Persist;
use RemoteModels\NextPage;
use RemoteModels\RemoteModel;

#[Endpoint('/v1/invoices', key: 'id', keyType: 'string')]
#[Persist('/v1/invoices/{key}', method: 'post')]
#[BracketFilters]
#[Paging(perPage: 'limit', page: null)]
class Invoice extends RemoteModel
{
    protected $fillable = ['customer', 'description'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'created' => 'immutable_datetime',
            'paid' => 'boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, array<string, mixed>>  $records
     */
    public function nextPage(Response $response, array $query, array $records): ?NextPage
    {
        if ($response->json('has_more') !== true || $records === []) {
            return null;
        }

        $last = end($records);

        return new NextPage(query: array_merge($query, [
            'starting_after' => $last['id'] ?? null,
        ]));
    }
}
