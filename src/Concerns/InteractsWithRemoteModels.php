<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RemoteModels\Attributes\RelationAttribute;
use RemoteModels\Relations\HasManyRemote;
use RemoteModels\Relations\HasOneRemote;
use RemoteModels\RemoteBuilder;
use RemoteModels\RemoteModel;

trait InteractsWithRemoteModels
{
    use ReadsRemoteAttributes;

    public static function bootInteractsWithRemoteModels(): void
    {
        foreach (static::remoteAttributes(RelationAttribute::class) as $attribute) {
            static::resolveRelationUsing(
                $attribute->name(),
                static fn (Model $model): Relation => $attribute->make($model),
            );
        }
    }

    /**
     * @param  class-string<RemoteModel>  $related
     */
    public function hasManyRemote(string $related, ?string $foreignKey = null, ?string $localKey = null): HasManyRemote
    {
        return new HasManyRemote($this->newRemoteRelatedQuery($related), $this, $foreignKey, $localKey);
    }

    /**
     * @param  class-string<RemoteModel>  $related
     */
    public function hasOneRemote(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOneRemote
    {
        return new HasOneRemote($this->newRemoteRelatedQuery($related), $this, $foreignKey, $localKey);
    }

    /**
     * @param  class-string<RemoteModel>  $related
     * @return RemoteBuilder<RemoteModel>
     */
    protected function newRemoteRelatedQuery(string $related): RemoteBuilder
    {
        return (new $related)->newQuery();
    }
}
