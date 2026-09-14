<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RemoteModels\Exceptions\RelationNotSupported;
use RemoteModels\Relations\BelongsToLocal as BelongsToLocalRelation;
use RemoteModels\RemoteModel;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class BelongsToLocal extends RelationAttribute
{
    /**
     * @param  class-string<Model>  $related
     */
    public function __construct(
        public string $related,
        public string $remoteKey,
        public ?string $ownerKey = null,
        public ?string $as = null,
    ) {}

    public function name(): string
    {
        return $this->as ?? Str::camel(class_basename($this->related));
    }

    public function make(Model $model): BelongsToLocalRelation
    {
        if (! $model instanceof RemoteModel) {
            throw RelationNotSupported::remoteParent(self::class, $model::class);
        }

        $instance = new $this->related;

        return new BelongsToLocalRelation(
            $instance->newQuery(),
            $model,
            $this->remoteKey,
            $this->ownerKey ?? $instance->getKeyName(),
        );
    }
}
