<?php

declare(strict_types=1);

namespace RemoteModels\Exceptions;

use LogicException;

final class UnsupportedEagerLoad extends LogicException
{
    public static function via(string $relation, string $uri): self
    {
        return new self("Relation [{$relation}] cannot be eager loaded with the fixed uri [{$uri}]. Use a template such as /repos/{key}/issues, or a closure that receives the parent model.");
    }
}
