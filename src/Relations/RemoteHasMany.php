<?php

declare(strict_types=1);

namespace RemoteModels\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use RemoteModels\RemoteModel;

/**
 * @extends RemoteRelation<EloquentCollection<int, RemoteModel>>
 */
class RemoteHasMany extends RemoteRelation
{
    /**
     * @return EloquentCollection<int, RemoteModel>
     */
    public function getResults(): EloquentCollection
    {
        return $this->remote->get();
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, Model>
     */
    public function initRelation(array $models, mixed $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * @return EloquentCollection<int, RemoteModel>
     */
    protected function matched(Model $parent): EloquentCollection
    {
        return $this->resultsFor($parent);
    }
}
