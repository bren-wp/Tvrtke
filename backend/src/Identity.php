<?php
declare(strict_types=1);

namespace Tvrtke;

final class Identity
{
    public static function mbs(mixed $value): string
    {
        $digits = (string) $value;
        if (!preg_match('/^[0-9]{1,9}$/D', $digits)) {
            throw new \InvalidArgumentException('Neispravan MBS');
        }
        return str_pad($digits, 9, '0', STR_PAD_LEFT);
    }

    public static function oib(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = (string) $value;
        if (!preg_match('/^[0-9]{1,11}$/D', $digits)) {
            throw new \InvalidArgumentException('Neispravan OIB');
        }
        return str_pad($digits, 11, '0', STR_PAD_LEFT);
    }

    public static function date(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = substr($value, 0, 10);
        $obj = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $obj && $obj->format('Y-m-d') === $date ? $date : null;
    }
}
