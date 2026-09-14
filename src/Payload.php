<?php

declare(strict_types=1);

namespace RemoteModels;

final class Payload
{
    public static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    public static function isRecord(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    /**
     * @return array<string, mixed>
     */
    public static function record(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $record = [];

        foreach ($value as $key => $item) {
            $record[(string) $key] = $item;
        }

        return $record;
    }

    /**
     * @return array<int, string>
     */
    public static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function records(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $records = [];

        foreach ($value as $item) {
            $records[] = self::record($item);
        }

        return $records;
    }
}
