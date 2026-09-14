<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;
use Illuminate\Http\Client\Response;
use RemoteModels\NextPage;

#[Attribute(Attribute::TARGET_CLASS)]
final class PagePagination extends Pagination
{
    public function __construct(
        public string $pageName = 'page',
        public string $perPageName = 'per_page',
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, array<string, mixed>>  $records
     */
    public function next(Response $response, array $query, array $records): ?NextPage
    {
        if ($records === []) {
            return null;
        }

        $perPage = $query[$this->perPageName] ?? null;

        if (is_int($perPage) && count($records) < $perPage) {
            return null;
        }

        $page = $query[$this->pageName] ?? 1;

        return new NextPage(null, array_merge($query, [
            $this->pageName => (is_int($page) ? $page : 1) + 1,
        ]));
    }
}
