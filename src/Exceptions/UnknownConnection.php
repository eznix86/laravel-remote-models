<?php

declare(strict_types=1);

namespace RemoteModels\Exceptions;

use InvalidArgumentException;

final class UnknownConnection extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("Remote connection [{$name}] is not defined in config/remote.php.");
    }

    public static function missingFor(string $model): self
    {
        return new self("Model [{$model}] has no remote connection. Set \$connection or #[Connection], put the model under a Remote\\{Connection} namespace, or set remote.default.");
    }

    public static function withoutUrl(string $name): self
    {
        return new self("Remote connection [{$name}] has no url.");
    }
}
