<?php

declare(strict_types=1);

namespace RemoteModels;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\Exceptions\UnresolvedUri;

final class UriTemplate
{
    public static function expand(string $uri, Model $model, string|int|null $key = null): string
    {
        $expanded = preg_replace_callback(
            '/\{([A-Za-z0-9_.]+)\}/',
            static fn (array $matches): string => self::value($matches[1], $uri, $model, $key),
            $uri,
        );

        return $expanded ?? $uri;
    }

    public static function isTemplate(string $uri): bool
    {
        return str_contains($uri, '{');
    }

    private static function value(string $name, string $uri, Model $model, string|int|null $key): string
    {
        $value = match (true) {
            $key !== null && in_array($name, ['key', 'id'], true) => $key,
            in_array($name, ['key', 'id'], true) => $model->getKey(),
            default => data_get($model, $name),
        };

        if (! is_string($value) && ! is_int($value)) {
            throw UnresolvedUri::placeholder($name, $uri, $model::class);
        }

        if ($value === '') {
            throw UnresolvedUri::placeholder($name, $uri, $model::class);
        }

        return (string) $value;
    }
}
