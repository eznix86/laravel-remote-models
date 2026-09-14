<?php

declare(strict_types=1);

namespace RemoteModels\Relations;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\RemoteModel;

/**
 * @extends RemoteRelation<RemoteModel|null>
 */
class HasOneRemote extends RemoteRelation
{
    public function getResults(): ?RemoteModel
    {
        return $this->remote->first();
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

    protected function matched(Model $parent): ?RemoteModel
    {
        $results = $this->resultsFor($parent)->all();

        return $results[0] ?? null;
    }
}
