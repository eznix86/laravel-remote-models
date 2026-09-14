<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RemoteModels\Relations\RemoteHasOne as RemoteHasOneRelation;
use RemoteModels\RemoteModel;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RemoteHasOne extends RelationAttribute
{
    /**
     * @param  class-string<RemoteModel>  $related
     * @param  string|array<string, mixed>|null  $on
     */
    public function __construct(
        public string $related,
        public ?string $as = null,
        public ?string $via = null,
        public ?string $foreignKey = null,
        public ?string $localKey = null,
        public string|array|null $on = null,
    ) {}

    public function name(): string
    {
        return $this->as ?? Str::camel(class_basename($this->related));
    }

    public function make(Model $model): RemoteHasOneRelation
    {
        $relation = new RemoteHasOneRelation(
            (new $this->related)->newQuery(),
            $model,
            $this->foreignKey,
            $this->localKey,
        );

        if ($this->via !== null) {
            $relation->via($this->via);
        }

        if ($this->on !== null) {
            $relation->on($this->on);
        }

        return $relation;
    }
}
