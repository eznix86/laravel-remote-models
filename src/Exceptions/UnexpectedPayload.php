<?php

declare(strict_types=1);

namespace RemoteModels\Exceptions;

use RuntimeException;

final class UnexpectedPayload extends RuntimeException
{
    public static function notAList(string $model): self
    {
        return new self("Remote model [{$model}] expected the response to hold a list of records. Override records() on the model.");
    }

    public static function notARecord(string $model): self
    {
        return new self("Remote model [{$model}] expected the response to hold a single record. Override record() on the model.");
    }
}
