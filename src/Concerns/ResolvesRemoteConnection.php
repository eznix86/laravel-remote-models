<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use RemoteModels\Exceptions\UnknownConnection;
use RemoteModels\Facades\Remote;
use RemoteModels\RemoteConnection;

trait ResolvesRemoteConnection
{
    use ReadsRemoteAttributes;

    /**
     * @var array<string, mixed>|Model|null
     */
    protected array|Model|null $remoteConnectionOverride = null;

    public function getConnection(): RemoteConnection
    {
        $connection = $this->remoteConnection();

        return new RemoteConnection(is_string($connection) ? $connection : 'remote');
    }

    /**
     * @param  array<string, mixed>|Model  $connection
     */
    public function setRemoteConnection(array|Model $connection): static
    {
        $this->remoteConnectionOverride = $connection;

        return $this;
    }

    /**
     * @return array<string, mixed>|Model|null
     */
    public function getRemoteConnectionOverride(): array|Model|null
    {
        return $this->remoteConnectionOverride;
    }

    /**
     * @return string|array<string, mixed>|Model
     */
    public function remoteConnection(): string|array|Model
    {
        if ($this->remoteConnectionOverride !== null) {
            return $this->remoteConnectionOverride;
        }

        $connection = $this->getConnectionName();

        if (is_string($connection) && $connection !== '') {
            return $connection;
        }

        return static::defaultRemoteConnection();
    }

    public function newRemoteRequest(): PendingRequest
    {
        return Remote::connection($this->remoteConnection())->withHeaders($this->remoteHeaders());
    }

    protected static function defaultRemoteConnection(): string
    {
        $inferred = static::remoteConnectionFromNamespace();

        if ($inferred !== null) {
            return $inferred;
        }

        $default = config('remote.default');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        throw UnknownConnection::missingFor(static::class);
    }

    protected static function remoteConnectionFromNamespace(): ?string
    {
        $segments = explode('\\', static::class);

        $position = array_search('Remote', $segments, true);

        if ($position === false || $position + 1 >= count($segments) - 1) {
            return null;
        }

        return Str::kebab($segments[$position + 1]);
    }
}
