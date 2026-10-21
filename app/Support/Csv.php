<?php

namespace App\Support;

class Csv
{
    /** 表計算ソフトで式として解釈される値（CSV数式注入）を無害化する。 */
    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! preg_match('/^-?\d+$/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    public static function row(array $values): array
    {
        return array_map([self::class, 'cell'], $values);
    }
}
