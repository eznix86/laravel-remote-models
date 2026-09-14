<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionMethod;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Headers;
use RemoteModels\Attributes\Index;
use RemoteModels\Attributes\Persist;
use RemoteModels\Attributes\Remove;
use RemoteModels\Attributes\Route;
use RemoteModels\Attributes\Show;
use RemoteModels\Attributes\Store;
use RemoteModels\Exceptions\UnexpectedPayload;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Facades\Remote;
use RemoteModels\Payload;
use RemoteModels\RemoteModel;
use RemoteModels\UriTemplate;

trait SendsRemoteRequests
{
    use ReadsRemoteAttributes;

    /**
     * @param  array<string, mixed>  $query
     */
    public function remoteIndex(array $query): Response
    {
        return $this->index($this->newRemoteRequest(), $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function remoteIndexAt(string $uri, array $query): Response
    {
        return $this->dispatch($this->newRemoteRequest(), 'get', $uri, $query);
    }

    public function remoteUriFor(string $verb): string
    {
        [, $uri] = match ($verb) {
            'index' => $this->remoteRoute(Index::class, 'get', $this->endpointPath()),
            'show' => $this->remoteRoute(Show::class, 'get', $this->endpointPath().'/{key}'),
            'store' => $this->remoteRoute(Store::class, 'post', $this->endpointPath()),
            'persist' => $this->remoteRoute(Persist::class, 'patch', $this->endpointPath().'/{key}'),
            'remove' => $this->remoteRoute(Remove::class, 'delete', $this->endpointPath().'/{key}'),
            default => throw UnsupportedQuery::method($verb, static::class),
        };

        return $this->expandUri($uri);
    }

    public function remoteUrlFor(string $verb): string
    {
        return ltrim(rtrim($this->remoteBaseUrl(), '/').'/'.ltrim($this->remoteUriFor($verb), '/'), '/');
    }

    public function remoteBaseUrl(): string
    {
        $url = Remote::configuration($this->remoteConnection())['url'] ?? '';

        return is_string($url) ? rtrim($url, '/') : '';
    }

    public function remoteIndexIsPoolable(): bool
    {
        if ($this->remoteCacheSeconds() !== null) {
            return false;
        }

        return new ReflectionMethod(static::class, 'index')->getDeclaringClass()->getName() === RemoteModel::class;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function remoteIndexPromise(?string $uri, array $query): mixed
    {
        $method = 'get';

        if ($uri === null) {
            [$method, $route] = $this->remoteRoute(Index::class, 'get', $this->endpointPath());

            $uri = $this->expandUri($route);
        }

        return $this->call($this->newRemoteRequest()->async(), $method, $uri, $query);
    }

    public function remoteShow(string|int $id): Response
    {
        return $this->show($this->newRemoteRequest(), $id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function remoteStore(array $attributes): Response
    {
        return $this->store($this->newRemoteRequest(), $attributes);
    }

    /**
     * @param  array<string, mixed>  $dirty
     */
    public function remotePersist(array $dirty): Response
    {
        return $this->persist($this->newRemoteRequest(), $dirty);
    }

    public function remoteRemove(): Response
    {
        return $this->remove($this->newRemoteRequest());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function records(Response $response): array
    {
        $payload = $response->json();

        if (Payload::isList($payload)) {
            return Payload::records($payload);
        }

        if (is_array($payload) && Payload::isList($payload['data'] ?? null)) {
            return Payload::records($payload['data']);
        }

        throw UnexpectedPayload::notAList(static::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function record(Response $response): array
    {
        $payload = $response->json();

        if ($payload === null || $payload === []) {
            return [];
        }

        if (is_array($payload) && Payload::isRecord($payload['data'] ?? null)) {
            return Payload::record($payload['data']);
        }

        if (Payload::isRecord($payload)) {
            return Payload::record($payload);
        }

        throw UnexpectedPayload::notARecord(static::class);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function index(PendingRequest $http, array $query): Response
    {
        [$method, $uri] = $this->remoteRoute(Index::class, 'get', $this->endpointPath());

        return $this->dispatch($http, $method, $this->expandUri($uri), $query);
    }

    protected function show(PendingRequest $http, string|int $id): Response
    {
        [$method, $uri] = $this->remoteRoute(Show::class, 'get', $this->endpointPath().'/{key}');

        return $this->dispatch($http, $method, $this->expandUri($uri, $id), $this->keyPayload($uri, $id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function store(PendingRequest $http, array $attributes): Response
    {
        [$method, $uri] = $this->remoteRoute(Store::class, 'post', $this->endpointPath());

        return $this->dispatch($http, $method, $this->expandUri($uri), $attributes);
    }

    /**
     * @param  array<string, mixed>  $dirty
     */
    protected function persist(PendingRequest $http, array $dirty): Response
    {
        [$method, $uri] = $this->remoteRoute(Persist::class, 'patch', $this->endpointPath().'/{key}');

        $payload = array_merge($this->keyPayload($uri, $this->getKey()), $dirty);

        return $this->dispatch($http, $method, $this->expandUri($uri), $payload);
    }

    protected function remove(PendingRequest $http): Response
    {
        [$method, $uri] = $this->remoteRoute(Remove::class, 'delete', $this->endpointPath().'/{key}');

        return $this->dispatch($http, $method, $this->expandUri($uri), $this->keyPayload($uri, $this->getKey()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function keyPayload(string $uri, mixed $key): array
    {
        if (UriTemplate::isTemplate($uri)) {
            return [];
        }

        if (! is_string($key) && ! is_int($key)) {
            return [];
        }

        return [$this->getKeyName() => $key];
    }

    protected function endpointPath(): string
    {
        $endpoint = static::remoteAttribute(Endpoint::class);

        if ($endpoint instanceof Endpoint) {
            return $endpoint->path;
        }

        return '/'.Str::kebab(Str::pluralStudly(class_basename(static::class)));
    }

    /**
     * @return array<string, string>
     */
    protected function remoteHeaders(): array
    {
        $headers = static::remoteAttribute(Headers::class);

        return $headers instanceof Headers ? $headers->headers : [];
    }

    /**
     * @param  class-string<Route>  $attribute
     * @return array{string, string}
     */
    protected function remoteRoute(string $attribute, string $method, string $uri): array
    {
        $route = static::remoteAttribute($attribute);

        return $route instanceof Route ? [$route->method, $route->uri] : [$method, $uri];
    }

    public function expandUri(string $uri, string|int|null $key = null): string
    {
        return UriTemplate::expand($uri, $this, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function dispatch(PendingRequest $http, string $method, string $uri, array $data): Response
    {
        $seconds = $this->remoteCacheSeconds();

        if ($seconds === null || ! in_array(strtolower($method), ['get', 'head'], true)) {
            return $this->send($http, $method, $uri, $data);
        }

        $cached = Cache::store($this->remoteCacheStore())->remember(
            $this->remoteCacheKey($method, $uri, $data),
            $seconds,
            function () use ($http, $method, $uri, $data): array {
                $response = $this->send($http, $method, $uri, $data)->throw();

                return [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ];
            },
        );

        return $this->remoteCachedResponse($cached);
    }

    protected function remoteShowCacheKey(): string
    {
        [$method, $uri] = $this->remoteRoute(Show::class, 'get', $this->endpointPath().'/{key}');

        return $this->remoteCacheKey($method, $this->expandUri($uri), []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function send(PendingRequest $http, string $method, string $uri, array $data): Response
    {
        $response = $this->call($http, $method, $uri, $data);

        if (! $response instanceof Response) {
            throw UnsupportedQuery::asynchronous(static::class);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function call(PendingRequest $http, string $method, string $uri, array $data): mixed
    {
        return match (strtolower($method)) {
            'get' => $data === [] ? $http->get($uri) : $http->get($uri, $data),
            'head' => $data === [] ? $http->head($uri) : $http->head($uri, $data),
            'post' => $http->post($uri, $data),
            'put' => $http->put($uri, $data),
            'patch' => $http->patch($uri, $data),
            'delete' => $http->delete($uri, $data),
            default => throw UnsupportedQuery::method($method, static::class),
        };
    }
}
