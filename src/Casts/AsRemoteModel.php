<?php

declare(strict_types=1);

namespace RemoteModels\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RemoteModels\Exceptions\UnexpectedPayload;
use RemoteModels\Payload;
use RemoteModels\RemoteModel;

/**
 * @implements CastsAttributes<RemoteModel, mixed>
 */
class AsRemoteModel implements CastsAttributes
{
    /**
     * @param  class-string<RemoteModel>  $related
     */
    public function __construct(protected string $related) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RemoteModel
    {
        $record = is_string($value) ? json_decode($value, true) : $value;

        if ($record === null) {
            return null;
        }

        if (! is_array($record)) {
            throw UnexpectedPayload::notARecord($this->related);
        }

        $related = new $this->related;

        $connection = $related->getConnectionName();

        if (($connection === null || $connection === '') && $model instanceof RemoteModel) {
            $connection = $model->getConnectionName();
        }

        return $related->newFromBuilder(Payload::record($record), $connection);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof RemoteModel) {
            return [$key => $value->getAttributes()];
        }

        if (is_array($value)) {
            return [$key => $value];
        }

        throw UnexpectedPayload::notARecord($this->related);
    }
}
