<?php

declare(strict_types=1);

namespace RemoteModels\Exceptions;

use LogicException;

final class RelationNotSupported extends LogicException
{
    public static function remoteParent(string $attribute, string $model): self
    {
        return new self("Attribute [{$attribute}] needs a RemoteModel to declare it, [{$model}] is an Eloquent model.");
    }
}
