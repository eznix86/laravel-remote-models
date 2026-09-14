<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Http\Client\Response;
use RemoteModels\Attributes\LinkPagination;
use RemoteModels\Attributes\Pagination;
use RemoteModels\NextPage;

trait PaginatesRemoteResults
{
    use ReadsRemoteAttributes;

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, array<string, mixed>>  $records
     */
    public function nextPage(Response $response, array $query, array $records): ?NextPage
    {
        $pagination = static::remoteAttribute(Pagination::class) ?? new LinkPagination;

        return $pagination->next($response, $query, $records);
    }

    public function total(Response $response): ?int
    {
        foreach (['total', 'total_count', 'meta.total'] as $path) {
            $total = $response->json($path);

            if (is_int($total)) {
                return $total;
            }
        }

        return null;
    }
}
