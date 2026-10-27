<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Data;

use Dainc007\DiscordRestClient\Exceptions\DiscordApiException;

/**
 * Reads typed values out of a decoded Discord payload, tolerating missing
 * optional fields and failing loudly on missing required ones.
 *
 * @internal
 */
final class Attributes
{
    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function requiredString(array $attributes, string $key, string $object): string
    {
        $value = $attributes[$key] ?? null;

        if (! is_string($value) && ! is_int($value)) {
            throw new DiscordApiException("Discord {$object} payload is missing required field [{$key}].");
        }

        return (string) $value;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function string(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function int(array $attributes, string $key, int $default = 0): int
    {
        $value = $attributes[$key] ?? null;

        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function nullableInt(array $attributes, string $key): ?int
    {
        $value = $attributes[$key] ?? null;

        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function bool(array $attributes, string $key): bool
    {
        return ($attributes[$key] ?? false) === true;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    public static function array(array $attributes, string $key): array
    {
        $value = $attributes[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return list<string>
     */
    public static function stringList(array $attributes, string $key): array
    {
        $strings = [];

        foreach (self::array($attributes, $key) as $value) {
            if (is_string($value) || is_int($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }
}
