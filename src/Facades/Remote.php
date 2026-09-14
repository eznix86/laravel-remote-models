<?php

declare(strict_types=1);

namespace RemoteModels\Facades;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Facade;
use RemoteModels\RemoteManager;

/**
 * @method static void extend(string $name, Closure $resolver)
 * @method static PendingRequest connection(string|array<string, mixed>|Model $connection)
 * @method static array<string, mixed> configuration(string|array<string, mixed>|Model $connection)
 *
 * @see RemoteManager
 */
class Remote extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RemoteManager::class;
    }
}
