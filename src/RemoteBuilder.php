<?php

declare(strict_types=1);

namespace RemoteModels;

use Closure;
use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Uri;
use RemoteModels\Exceptions\UnresolvedUri;
use RemoteModels\Exceptions\UnsupportedQuery;

/**
 * @template TModel of RemoteModel
 *
 * @extends Builder<TModel>
 */
class RemoteBuilder extends Builder
{
    protected ?string $remoteUri = null;

    /**
     * @var array<string, mixed>
     */
    protected array $remoteQuery = [];

    public function via(string|Uri $uri): static
    {
        $uri = (string) $uri;

        if (UriTemplate::isTemplate($uri)) {
            throw UnresolvedUri::template($uri, $this->model::class);
        }

        $parsed = Uri::of($uri);

        $this->remoteUri = (string) $parsed->replaceQuery([]);
        $this->remoteQuery = Payload::record($parsed->query()->all());

        return $this;
    }

    /**
     * @return array<int, TModel>
     */
    public function getModels(mixed $columns = ['*']): array
    {
        $response = $this->sendIndex($this->toRemoteQuery());

        return array_values($this->hydrate($this->model->records($response))->all());
    }

    /**
     * @return LazyCollection<int, TModel>
     */
    public function cursor(): LazyCollection
    {
        $query = $this->toRemoteQuery();
        $uri = $this->remoteUri;

        return LazyCollection::make(function () use ($query, $uri): Generator {
            while (true) {
                $response = $this->sendIndex($query, $uri);

                $records = $this->model->records($response);

                foreach ($this->hydrate($records)->all() as $model) {
                    yield $model;
                }

                $next = $this->model->nextPage($response, $query, $records);

                if ($next === null) {
                    return;
                }

                if (($next->uri ?? $uri) === $uri && $next->query === $query) {
                    return;
                }

                $uri = $next->uri ?? $uri;
                $query = $next->query;
            }
        });
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(
        mixed $perPage = null,
        mixed $columns = ['*'],
        mixed $pageName = 'page',
        mixed $page = null,
        mixed $total = null,
    ): LengthAwarePaginator {
        $page = is_int($page) ? $page : Paginator::resolveCurrentPage($pageName);
        $perPage = is_int($perPage) ? $perPage : $this->model->getPerPage();

        $this->forPage($page, $perPage);

        $response = $this->sendIndex($this->toRemoteQuery());

        $resolved = is_int($total) ? $total : $this->model->total($response);

        if ($resolved === null) {
            throw UnsupportedQuery::total($this->model::class);
        }

        return new LengthAwarePaginator(
            $this->model->newCollection($this->hydrate($this->model->records($response))->all()),
            $resolved,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        );
    }

    /**
     * @return Paginator<int, TModel>
     */
    public function simplePaginate(
        mixed $perPage = null,
        mixed $columns = ['*'],
        mixed $pageName = 'page',
        mixed $page = null,
    ): Paginator {
        $page = is_int($page) ? $page : Paginator::resolveCurrentPage($pageName);
        $perPage = is_int($perPage) ? $perPage : $this->model->getPerPage();

        $this->forPage($page, $perPage);

        $query = $this->toRemoteQuery();
        $response = $this->sendIndex($query);
        $records = $this->model->records($response);

        $paginator = new Paginator(
            $this->model->newCollection($this->hydrate($records)->all()),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        );

        $next = $this->model->nextPage($response, $query, $records);

        return $paginator->hasMorePagesWhen($next !== null);
    }

    public function isPoolable(): bool
    {
        return $this->model->remoteIndexIsPoolable();
    }

    public function poolPromise(): mixed
    {
        return $this->model->remoteIndexPromise($this->remoteUri, $this->toRemoteQuery());
    }

    /**
     * @return EloquentCollection<int, TModel>
     */
    public function getFromResponse(Response $response): EloquentCollection
    {
        $models = array_values($this->hydrate($this->model->records($response->throw()))->all());

        if ($models !== []) {
            $models = $this->eagerLoadRelations($models);
        }

        return $this->model->newCollection($models);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function sendIndex(array $query, ?string $uri = null): Response
    {
        $uri ??= $this->remoteUri;

        $response = $uri === null
            ? $this->model->remoteIndex($query)
            : $this->model->remoteIndexAt($uri, $query);

        return $response->throw();
    }

    public function find(mixed $id, mixed $columns = ['*']): mixed
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $response = $this->model->remoteShow($id);

        if ($response->notFound()) {
            return null;
        }

        return $this->newModelInstance()->newFromBuilder($this->model->record($response->throw()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function newModelInstance(mixed $attributes = []): RemoteModel
    {
        $instance = parent::newModelInstance($attributes);

        $instance->setConnection($this->model->getConnectionName());

        $override = $this->model->getRemoteConnectionOverride();

        if ($override !== null) {
            $instance->setRemoteConnection($override);
        }

        return $instance;
    }

    public function has(
        mixed $relation,
        mixed $operator = '>=',
        mixed $count = 1,
        mixed $boolean = 'and',
        ?Closure $callback = null,
    ): static {
        throw UnsupportedQuery::relationExistence($this->model::class);
    }

    public function withAggregate(mixed $relations, mixed $column, mixed $function = null): static
    {
        if (blank($relations)) {
            return $this;
        }

        throw UnsupportedQuery::aggregate($this->model::class);
    }

    public function count(mixed $columns = '*'): int
    {
        return count($this->getModels());
    }

    public function exists(): bool
    {
        return $this->getModels() !== [];
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function insert(array $values): bool
    {
        throw UnsupportedQuery::massWrite('insert', $this->model::class);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw UnsupportedQuery::massWrite('update', $this->model::class);
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, mixed $uniqueBy, mixed $update = null): int
    {
        throw UnsupportedQuery::massWrite('upsert', $this->model::class);
    }

    public function delete(): mixed
    {
        throw UnsupportedQuery::massWrite('delete', $this->model::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRemoteQuery(): array
    {
        return array_merge($this->remoteQuery, $this->model->toQuery($this->getQuery()));
    }
}
