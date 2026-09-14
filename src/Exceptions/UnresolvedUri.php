<?php

declare(strict_types=1);

namespace RemoteModels\Exceptions;

use RuntimeException;

final class UnresolvedUri extends RuntimeException
{
    public static function closure(string $relation): self
    {
        return new self("Relation [{$relation}] was given a via closure that returned neither a string nor a Uri.");
    }

    public static function template(string $uri, string $model): self
    {
        return new self("Remote model [{$model}] was given the uri template [{$uri}]. Expand the placeholders before calling via(), or declare the relation with via().");
    }

    public static function placeholder(string $name, string $uri, string $model): self
    {
        return new self("Remote model [{$model}] has no value for [{$name}] in the uri [{$uri}].");
    }
}
