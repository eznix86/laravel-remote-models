<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use Illuminate\Http\Client\Response;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteModel;

#[Endpoint('/issues')]
class Issue extends RemoteModel
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function records(Response $response): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json('items', []);

        return $items;
    }
}
