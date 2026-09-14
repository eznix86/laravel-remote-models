<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use RemoteModels\NextPage;
use RemoteModels\Payload;

#[Attribute(Attribute::TARGET_CLASS)]
final class LinkPagination extends Pagination
{
    public function __construct(
        public string $rel = 'next',
        public string $header = 'Link',
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, array<string, mixed>>  $records
     */
    public function next(Response $response, array $query, array $records): ?NextPage
    {
        $link = Str::match('/<([^>]+)>;\s*rel="'.preg_quote($this->rel, '/').'"/', $response->header($this->header));

        if ($link === '') {
            return null;
        }

        $uri = Uri::of($link);

        return new NextPage(
            (string) $uri->replaceQuery([]),
            array_merge($query, Payload::record($uri->query()->all())),
        );
    }
}
