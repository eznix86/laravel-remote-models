<?php

declare(strict_types=1);

namespace RemoteModels\Relations;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Uri;
use RemoteModels\Exceptions\UnresolvedUri;
use RemoteModels\Exceptions\UnsupportedEagerLoad;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Payload;
use RemoteModels\RemoteBuilder;
use RemoteModels\RemoteModel;
use RemoteModels\UriTemplate;

/**
 * @template TResult
 *
 * @extends Relation<RemoteModel, Model, TResult>
 */
abstract class RemoteRelation extends Relation
{
    protected string|Closure|null $via = null;

    /**
     * @var string|array<string, mixed>|Model|Closure|null
     */
    protected string|array|Model|Closure|null $connection = null;

    /**
     * @var array<int, Model>
     */
    protected array $eagerParents = [];

    /**
     * @var array<int, EloquentCollection<int, RemoteModel>>
     */
    protected array $eagerResults = [];

    /**
     * @param  RemoteBuilder<RemoteModel>  $remote
     */
    public function __construct(
        protected RemoteBuilder $remote,
        Model $parent,
        protected ?string $foreignKey = null,
        protected ?string $localKey = null,
    ) {
        parent::__construct($this->remote, $parent);
    }

    public function via(string|Closure|Uri $uri): static
    {
        $this->via = $uri instanceof Uri ? (string) $uri : $uri;

        if (static::$constraints) {
            $this->remote->via($this->resolveVia($this->via, $this->parent));
        }

        return $this;
    }

    /**
     * @param  string|array<string, mixed>|Model|Closure  $connection
     */
    public function on(string|array|Model|Closure $connection): static
    {
        $this->connection = $connection;

        if (static::$constraints) {
            $this->applyConnection($this->remote, $this->parent);
        }

        return $this;
    }

    public function addConstraints(): void
    {
        if (! static::$constraints || $this->foreignKey === null) {
            return;
        }

        $this->remote->where($this->foreignKey, $this->parentValue($this->parent));
    }

    /**
     * @param  array<int, Model>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->eagerParents = $models;
    }

    /**
     * @return EloquentCollection<int, RemoteModel>
     */
    public function getEager(): EloquentCollection
    {
        if (is_string($this->via) && ! UriTemplate::isTemplate($this->via)) {
            throw UnsupportedEagerLoad::via(static::class, $this->via);
        }

        $this->eagerResults = [];

        $queries = [];

        foreach ($this->eagerParents as $parent) {
            $queries[spl_object_id($parent)] = $this->queryFor($parent);
        }

        $responses = $this->pooled($queries);

        /** @var EloquentCollection<int, RemoteModel> $all */
        $all = $this->related->newCollection();

        foreach ($queries as $key => $query) {
            $results = isset($responses[$key]) ? $query->getFromResponse($responses[$key]) : $query->get();

            $this->eagerResults[$key] = $results;

            foreach ($results as $result) {
                $all->push($result);
            }
        }

        return $all;
    }

    /**
     * @param  array<int, RemoteBuilder<RemoteModel>>  $queries
     * @return array<int, Response>
     */
    protected function pooled(array $queries): array
    {
        $poolable = array_filter($queries, static fn (RemoteBuilder $query): bool => $query->isPoolable());

        if (count($poolable) < 2) {
            return [];
        }

        $promises = [];

        foreach ($poolable as $key => $query) {
            $promises[$key] = $query->poolPromise();
        }

        $resolved = [];

        foreach ($promises as $key => $promise) {
            $resolved[$key] = $this->await($promise);
        }

        return $resolved;
    }

    protected function await(mixed $promise): Response
    {
        $response = $promise instanceof PromiseInterface ? $promise->wait() : $promise;

        if (! $response instanceof Response) {
            throw UnsupportedQuery::asynchronous(static::class);
        }

        return $response;
    }

    /**
     * @param  array<int, Model>  $models
     * @param  EloquentCollection<int, RemoteModel>  $results
     * @return array<int, Model>
     */
    public function match(array $models, EloquentCollection $results, mixed $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->matched($model));
        }

        return $models;
    }

    /**
     * @return EloquentCollection<int, RemoteModel>
     */
    protected function resultsFor(Model $parent): EloquentCollection
    {
        return $this->eagerResults[spl_object_id($parent)] ?? $this->related->newCollection();
    }

    /**
     * @return EloquentCollection<int, RemoteModel>|RemoteModel|null
     */
    abstract protected function matched(Model $parent): EloquentCollection|RemoteModel|null;

    /**
     * @return RemoteBuilder<RemoteModel>
     */
    protected function queryFor(Model $parent): RemoteBuilder
    {
        $query = clone $this->remote;

        $query->setModel($this->related->newInstance());

        if ($this->via !== null) {
            $query->via($this->resolveVia($this->via, $parent));
        }

        if ($this->foreignKey !== null) {
            $query->where($this->foreignKey, $this->parentValue($parent));
        }

        if ($this->connection !== null) {
            $this->applyConnection($query, $parent);
        }

        return $query;
    }

    protected function resolveVia(string|Closure $uri, Model $parent): string
    {
        if ($uri instanceof Closure) {
            $resolved = $uri($parent);

            if ($resolved instanceof Uri) {
                return (string) $resolved;
            }

            if (! is_string($resolved)) {
                throw UnresolvedUri::closure(static::class);
            }

            return $resolved;
        }

        return UriTemplate::isTemplate($uri) ? UriTemplate::expand($uri, $parent) : $uri;
    }

    /**
     * @param  RemoteBuilder<RemoteModel>  $query
     */
    protected function applyConnection(RemoteBuilder $query, Model $parent): void
    {
        $connection = $this->connection instanceof Closure
            ? ($this->connection)($parent)
            : $this->connection;

        $model = $query->getModel();

        if ($connection instanceof Model) {
            $model->setRemoteConnection($connection);

            return;
        }

        if (is_array($connection)) {
            $model->setRemoteConnection(Payload::record($connection));

            return;
        }

        if (is_string($connection)) {
            $model->setConnection($connection);
        }
    }

    protected function parentValue(Model $parent): mixed
    {
        return $this->localKey === null ? $parent->getKey() : data_get($parent, $this->localKey);
    }
}
