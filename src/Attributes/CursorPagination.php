<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;
use Illuminate\Http\Client\Response;
use RemoteModels\NextPage;

#[Attribute(Attribute::TARGET_CLASS)]
final class CursorPagination extends Pagination
{
    public function __construct(
        public string $parameter = 'cursor',
        public string $path = 'meta.next_cursor',
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, array<string, mixed>>  $records
     */
    public function next(Response $response, array $query, array $records): ?NextPage
    {
        $cursor = $response->json($this->path);

        if (! is_string($cursor) || $cursor === '') {
            return null;
        }

        return new NextPage(null, array_merge($query, [$this->parameter => $cursor]));
    }
}
