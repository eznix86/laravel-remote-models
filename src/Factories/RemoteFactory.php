<?php

declare(strict_types=1);

namespace RemoteModels\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Payload;
use RemoteModels\Relations\BelongsToLocal;
use RemoteModels\RemoteModel;

/**
 * @template TModel of RemoteModel
 *
 * @extends Factory<TModel>
 */
abstract class RemoteFactory extends Factory
{
    /**
     * @param  Factory<covariant Model>|Model  $factory
     */
    public function for(mixed $factory, mixed $relationship = null): static
    {
        if ($factory instanceof Model) {
            $relation = $this->localRelation($factory, $relationship);

            if ($relation instanceof BelongsToLocal) {
                return $this->state(Payload::record(Arr::undot([
                    $relation->getRemoteKey() => $factory->getAttribute($relation->getOwnerKey()),
                ])));
            }
        }

        return parent::for($factory, $relationship);
    }

    protected function store(Collection $results): void
    {
        if ($this->has->isNotEmpty()) {
            throw UnsupportedQuery::factoryHas(static::class);
        }

        foreach ($results as $model) {
            $this->fake($model);
        }
    }

    protected function fake(Model $model): void
    {
        if (! $model instanceof RemoteModel) {
            return;
        }

        $model->exists = true;
        $model->wasRecentlyCreated = true;

        $this->fakes()->record(
            $model->remoteUrlFor('index'),
            $model->remoteUrlFor('show'),
            $model->toRemotePayload(),
        );
    }

    protected function fakes(): FakeResponses
    {
        return app(FakeResponses::class);
    }

    protected function localRelation(Model $parent, ?string $relationship): mixed
    {
        $name = $relationship ?? Str::camel(class_basename($parent));

        $model = $this->newModel();

        if (! $model->isRelation($name)) {
            return null;
        }

        return $model->{$name}();
    }
}
