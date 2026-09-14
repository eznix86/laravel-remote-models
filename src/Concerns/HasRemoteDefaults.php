<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;

trait HasRemoteDefaults
{
    /**
     * @var array<string, array<string, bool>>
     */
    protected static array $remoteDefaults = [];

    public function initializeHasRemoteDefaults(): void
    {
        if (! static::declaresModelProperty('timestamps')) {
            $this->timestamps = false;
        }

        if (! static::declaresModelProperty('incrementing')) {
            $this->incrementing = false;
        }
    }

    protected static function declaresModelProperty(string $property): bool
    {
        $model = static::class;

        if (isset(static::$remoteDefaults[$model][$property])) {
            return static::$remoteDefaults[$model][$property];
        }

        $declaring = new ReflectionProperty($model, $property)->getDeclaringClass()->getName();

        return static::$remoteDefaults[$model][$property] = $declaring !== Model::class;
    }
}
