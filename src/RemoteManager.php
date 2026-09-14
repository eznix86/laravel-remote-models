<?php

declare(strict_types=1);

namespace RemoteModels;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnknownConnection;

class RemoteManager
{
    /**
     * @var array<string, Closure(array<string, mixed>): PendingRequest>
     */
    protected array $extensions = [];

    public function __construct(protected Repository $config) {}

    /**
     * @param  Closure(array<string, mixed>): PendingRequest  $resolver
     */
    public function extend(string $name, Closure $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * @param  string|array<string, mixed>|Model  $connection
     */
    public function connection(string|array|Model $connection): PendingRequest
    {
        $name = $this->name($connection);

        $configuration = $this->configuration($connection);

        if (isset($this->extensions[$name])) {
            return ($this->extensions[$name])($configuration);
        }

        return $this->client($name, $configuration);
    }

    /**
     * @param  string|array<string, mixed>|Model  $connection
     * @return array<string, mixed>
     */
    public function configuration(string|array|Model $connection): array
    {
        if (is_array($connection)) {
            return $connection;
        }

        if ($connection instanceof Model) {
            return $this->fromModel($connection);
        }

        $configuration = $this->config->get("remote.connections.{$connection}");

        if (! is_array($configuration)) {
            throw UnknownConnection::named($connection);
        }

        return Payload::record($configuration);
    }

    /**
     * @param  string|array<string, mixed>|Model  $connection
     */
    protected function name(string|array|Model $connection): string
    {
        if (is_string($connection)) {
            return $connection;
        }

        $name = is_array($connection) ? ($connection['name'] ?? null) : $connection->getAttribute('name');

        return is_string($name) ? $name : 'remote';
    }

    /**
     * @return array<string, mixed>
     */
    protected function fromModel(Model $model): array
    {
        if (method_exists($model, 'toRemoteConnection')) {
            /** @var array<string, mixed> $configuration */
            $configuration = $model->toRemoteConnection();

            return $configuration;
        }

        $configuration = [];

        foreach (['name', 'url', 'token', 'headers', 'timeout', 'retry'] as $key) {
            $value = $model->getAttribute($key);

            if ($value !== null) {
                $configuration[$key] = $value;
            }
        }

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function client(string $name, array $configuration): PendingRequest
    {
        $url = $configuration['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw UnknownConnection::withoutUrl($name);
        }

        $request = Http::baseUrl($url)->acceptJson()->asJson();

        $token = $configuration['token'] ?? null;

        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $headers = $configuration['headers'] ?? null;

        if (is_array($headers) && $headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $timeout = $configuration['timeout'] ?? null;

        if (is_int($timeout)) {
            $request = $request->timeout($timeout);
        }

        $retry = $configuration['retry'] ?? null;

        if (is_int($retry)) {
            $request = $request->retry($retry);
        }

        if (is_array($retry) && isset($retry['times']) && is_int($retry['times'])) {
            $sleep = $retry['sleep'] ?? 0;

            $request = $request->retry($retry['times'], is_int($sleep) ? $sleep : 0);
        }

        return $request;
    }
}
