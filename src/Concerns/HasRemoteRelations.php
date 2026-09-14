<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RemoteModels\Attributes\RelationAttribute;
use RemoteModels\Relations\BelongsToLocal;
use RemoteModels\Relations\RemoteHasMany;
use RemoteModels\Relations\RemoteHasOne;
use RemoteModels\RemoteBuilder;
use RemoteModels\RemoteModel;

trait HasRemoteRelations
{
    use ReadsRemoteAttributes;

    public static function bootHasRemoteRelations(): void
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
    public function remoteHasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): RemoteHasMany
    {
        return new RemoteHasMany($this->newRelatedRemoteQuery($related), $this, $foreignKey, $localKey);
    }

    /**
     * @param  class-string<RemoteModel>  $related
     */
    public function remoteHasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): RemoteHasOne
    {
        return new RemoteHasOne($this->newRelatedRemoteQuery($related), $this, $foreignKey, $localKey);
    }

    /**
     * @param  class-string<Model>  $related
     */
    public function belongsToLocal(string $related, string $remoteKey, ?string $ownerKey = null): BelongsToLocal
    {
        $instance = new $related;

        return new BelongsToLocal($instance->newQuery(), $this, $remoteKey, $ownerKey ?? $instance->getKeyName());
    }

    /**
     * @param  class-string<RemoteModel>  $related
     * @return RemoteBuilder<RemoteModel>
     */
    protected function newRelatedRemoteQuery(string $related): RemoteBuilder
    {
        return (new $related)->newQuery();
    }
}
