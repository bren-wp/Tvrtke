<?php
declare(strict_types=1);

namespace Tvrtke;

final class Config
{
    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === '') {
            throw new \RuntimeException('Missing configuration: ' . $key);
        }
        return $value;
    }
}
