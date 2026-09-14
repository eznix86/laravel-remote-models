<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use ReflectionAttribute;
use ReflectionClass;

trait ReadsRemoteAttributes
{
    /**
     * @var array<string, array<class-string, object|null>>
     */
    protected static array $resolvedRemoteAttributes = [];

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    protected static function remoteAttribute(string $attribute): ?object
    {
        $model = static::class;

        $resolved = static::$resolvedRemoteAttributes[$model] ?? [];

        if (array_key_exists($attribute, $resolved)) {
            $cached = $resolved[$attribute];

            return $cached instanceof $attribute ? $cached : null;
        }

        $found = null;
        $reflection = new ReflectionClass($model);

        do {
            $declared = $reflection->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

            if ($declared !== []) {
                $found = $declared[0]->newInstance();

                break;
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        static::$resolvedRemoteAttributes[$model][$attribute] = $found;

        return $found instanceof $attribute ? $found : null;
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return array<int, TAttribute>
     */
    protected static function remoteAttributes(string $attribute): array
    {
        $resolved = [];
        $reflection = new ReflectionClass(static::class);

        do {
            foreach ($reflection->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF) as $declared) {
                $resolved[] = $declared->newInstance();
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $resolved;
    }
}
