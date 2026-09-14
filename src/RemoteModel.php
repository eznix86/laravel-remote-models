<?php

declare(strict_types=1);

namespace RemoteModels;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Casts\AsRemoteModel;
use RemoteModels\Concerns\CachesRemoteResponses;
use RemoteModels\Concerns\HasRemoteDefaults;
use RemoteModels\Concerns\HasRemoteRelations;
use RemoteModels\Concerns\PaginatesRemoteResults;
use RemoteModels\Concerns\ResolvesRemoteConnection;
use RemoteModels\Concerns\SendsRemoteRequests;
use RemoteModels\Concerns\TranslatesRemoteQueries;
use RemoteModels\Exceptions\UnsupportedQuery;
use UnitEnum;

/**
 * @phpstan-consistent-constructor
 */
abstract class RemoteModel extends Model implements Castable
{
    use CachesRemoteResponses;
    use HasRemoteDefaults;
    use HasRemoteRelations;
    use PaginatesRemoteResults;
    use ResolvesRemoteConnection;
    use SendsRemoteRequests;
    use TranslatesRemoteQueries;

    /**
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): AsRemoteModel
    {
        return new AsRemoteModel(static::class);
    }

    /**
     * @param  string|array<string, mixed>|Model|UnitEnum|null  $connection
     * @return RemoteBuilder<static>
     */
    public static function on(mixed $connection = null): RemoteBuilder
    {
        $model = new static;

        if (is_array($connection) || $connection instanceof Model) {
            $model->setRemoteConnection($connection);
        } else {
            $model->setConnection(is_string($connection) ? $connection : null);
        }

        return $model->newQuery();
    }

    public function newFromBuilder(mixed $attributes = [], mixed $connection = null): static
    {
        $model = parent::newFromBuilder($this->encodeRemoteAttributes((array) $attributes), $connection);

        $override = $this->getRemoteConnectionOverride();

        if ($override !== null) {
            $model->setRemoteConnection($override);
        }

        return $model;
    }

    /**
     * @return RemoteBuilder<*>
     */
    public function newEloquentBuilder(mixed $query): RemoteBuilder
    {
        $builder = parent::newEloquentBuilder($query);

        if ($builder instanceof RemoteBuilder) {
            return $builder;
        }

        if ($builder::class !== Builder::class) {
            throw UnsupportedQuery::builder($builder::class, static::class);
        }

        return new RemoteBuilder($query);
    }

    public function getKeyName(): string
    {
        $key = parent::getKeyName();

        $endpoint = static::remoteAttribute(Endpoint::class);

        if ($endpoint instanceof Endpoint && $endpoint->key !== null && $key === 'id') {
            return $endpoint->key;
        }

        return $key;
    }

    public function getKeyType(): string
    {
        $type = parent::getKeyType();

        $endpoint = static::remoteAttribute(Endpoint::class);

        if ($endpoint instanceof Endpoint && $endpoint->keyType !== null && $type === 'int') {
            return $endpoint->keyType;
        }

        return $type;
    }

    public function resolveRouteBinding(mixed $value, mixed $field = null): ?Model
    {
        if (($field ?? $this->getRouteKeyName()) !== $this->getKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        $found = $this->newQuery()->find($value);

        return $found instanceof Model ? $found : null;
    }

    protected function performInsert(Builder $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $attributes = $this->decodeRemoteAttributes(Payload::record($this->getAttributesForInsert()));

        $response = $this->remoteStore($attributes)->throw();

        $this->fillFromRemote($this->record($response));

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    protected function performUpdate(Builder $query): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirtyForUpdate();

        if ($dirty === []) {
            return true;
        }

        $response = $this->remotePersist($this->decodeRemoteAttributes($dirty))->throw();

        $this->forgetRemoteCache();

        $this->syncChanges();

        $this->fillFromRemote($this->record($response));

        $this->fireModelEvent('updated', false);

        return true;
    }

    protected function performDeleteOnModel(): void
    {
        $this->remoteRemove()->throw();

        $this->forgetRemoteCache();

        $this->exists = false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRemotePayload(): array
    {
        return $this->decodeRemoteAttributes(Payload::record($this->getAttributes()));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function fillFromRemote(array $record): void
    {
        if ($record === []) {
            return;
        }

        $merged = array_merge($this->getAttributes(), $this->encodeRemoteAttributes($record));

        $this->setRawAttributes($merged, true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function encodeRemoteAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_array($value) && $this->isRemoteJsonCast($key)) {
                $attributes[$key] = $this->asJson($value);
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function decodeRemoteAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_string($value) && $this->isRemoteJsonCast($key)) {
                $attributes[$key] = $this->fromJson($value);
            }
        }

        return $attributes;
    }

    protected function isRemoteJsonCast(string $key): bool
    {
        if (! $this->hasCast($key)) {
            return false;
        }

        return in_array($this->getCastType($key), ['array', 'json', 'json:unicode', 'object', 'collection'], true);
    }
}
