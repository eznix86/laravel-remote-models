<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @template TFactory of Factory
 */
trait HasRemoteFactory
{
    /** @use HasFactory<TFactory> */
    use HasFactory;
}
