<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RemoteModels\Attributes\CacheFor;
use RemoteModels\Payload;

trait CachesRemoteResponses
{
    use ReadsRemoteAttributes;

    protected ?int $cacheFor = null;

    public function remoteCacheSeconds(): ?int
    {
        if ($this->cacheFor !== null) {
            return $this->cacheFor;
        }

        $cache = static::remoteAttribute(CacheFor::class);

        return $cache instanceof CacheFor ? $cache->seconds : null;
    }

    public function forgetRemoteCache(): void
    {
        if ($this->remoteCacheSeconds() === null) {
            return;
        }

        Cache::store($this->remoteCacheStore())->forget($this->remoteShowCacheKey());
    }

    protected function remoteCacheStore(): ?string
    {
        $cache = static::remoteAttribute(CacheFor::class);

        return $cache instanceof CacheFor ? $cache->store : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function remoteCacheKey(string $method, string $uri, array $data): string
    {
        $connection = $this->remoteConnection();

        return 'remote-models:'.hash('sha256', implode('|', [
            static::class,
            is_string($connection) ? $connection : 'remote',
            strtolower($method),
            $uri,
            (string) json_encode($data),
        ]));
    }

    protected function remoteCachedResponse(mixed $cached): Response
    {
        $cached = Payload::record($cached);

        $status = $cached['status'] ?? 200;
        $body = $cached['body'] ?? '';

        $headers = [];

        foreach (Payload::record($cached['headers'] ?? []) as $name => $value) {
            $headers[$name] = is_string($value) ? $value : Payload::strings($value);
        }

        return new Response(new Psr7Response(
            is_int($status) ? $status : 200,
            $headers,
            is_string($body) ? $body : '',
        ));
    }
}
