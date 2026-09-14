<?php

declare(strict_types=1);

namespace RemoteModels\Attributes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class RelationAttribute
{
    abstract public function name(): string;

    /**
     * @return Relation<*, *, *>
     */
    abstract public function make(Model $model): Relation;
}
