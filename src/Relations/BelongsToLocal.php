<?php

declare(strict_types=1);

namespace RemoteModels\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RemoteModels\RemoteModel;

/**
 * @extends Relation<Model, RemoteModel, Model|null>
 */
class BelongsToLocal extends Relation
{
    /**
     * @param  Builder<Model>  $query
     */
    public function __construct(
        Builder $query,
        RemoteModel $parent,
        protected string $remoteKey,
        protected string $ownerKey,
    ) {
        parent::__construct($query, $parent);
    }

    public function getRemoteKey(): string
    {
        return $this->remoteKey;
    }

    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    public function addConstraints(): void
    {
        if (! static::$constraints) {
            return;
        }

        $this->query->where($this->ownerKey, '=', $this->remoteValue($this->parent));
    }

    /**
     * @param  array<int, Model>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $values = [];

        foreach ($models as $model) {
            $value = $this->remoteValue($model);

            if (is_string($value) || is_int($value)) {
                $values[(string) $value] = $value;
            }
        }

        $this->query->whereIn($this->ownerKey, array_values($values));
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, Model>
     */
    public function initRelation(array $models, mixed $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * @param  array<int, Model>  $models
     * @param  EloquentCollection<int, Model>  $results
     * @return array<int, Model>
     */
    public function match(array $models, EloquentCollection $results, mixed $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $owner = $result->getAttribute($this->ownerKey);

            if (is_string($owner) || is_int($owner)) {
                $dictionary[(string) $owner] = $result;
            }
        }

        foreach ($models as $model) {
            $value = $this->remoteValue($model);

            $model->setRelation(
                $relation,
                is_string($value) || is_int($value) ? ($dictionary[(string) $value] ?? null) : null,
            );
        }

        return $models;
    }

    public function getResults(): ?Model
    {
        if ($this->remoteValue($this->parent) === null) {
            return null;
        }

        return $this->query->first();
    }

    protected function remoteValue(Model $model): mixed
    {
        return data_get($model, $this->remoteKey);
    }
}
