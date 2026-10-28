<?php

namespace App\Domain\Academy\Services;

/**
 * Declarative arithmetic for labs and "what if": no eval, only named operations over the value bag.
 * ops: divide, multiply, add, subtract, percent (a * b / 100), lookup (table[arg] ?? default), band (thresholds → label), const.
 */
class RuleEvaluator
{
    public function compute(array $rules, array $values): array
    {
        foreach ($rules as $rule) {
            $key = $rule['key'] ?? null;
            if (! $key) {
                continue;
            }
            $values[$key] = $this->one($rule, $values);
        }

        return $values;
    }

    private function one(array $rule, array $values): mixed
    {
        $num = fn (string $name) => $this->number($rule[$name] ?? null, $values);
        $result = match ($rule['op'] ?? 'const') {
            'divide' => $num('b') != 0.0 ? $num('a') / $num('b') : 0.0,
            'multiply' => $num('a') * $num('b'),
            'add' => $num('a') + $num('b'),
            'subtract' => $num('a') - $num('b'),
            'percent' => $num('a') * $num('b') / 100,
            'lookup' => (float) (($rule['table'] ?? [])[(string) ($values[$rule['arg'] ?? ''] ?? '')] ?? ($rule['default'] ?? 0)),
            'band' => $this->band($num('a'), $rule['bands'] ?? []),
            'max' => max($num('a'), $num('b')),
            'min' => min($num('a'), $num('b')),
            default => $rule['value'] ?? null,
        };
        if (is_float($result) || is_int($result)) {
            $round = $rule['round'] ?? 0;
            $result = $round === null ? $result : round((float) $result, (int) $round);
            if (! empty($rule['ceil'])) {
                $result = ceil($result);
            }
            if ((int) $round === 0) {
                $result = (int) $result;
            }
        }

        return $result;
    }

    /** A number literal, or a reference to a key in the bag ("budget"), or a numeric string. */
    private function number(mixed $ref, array $values): float
    {
        if (is_int($ref) || is_float($ref)) {
            return (float) $ref;
        }
        if (is_string($ref) && array_key_exists($ref, $values)) {
            return $this->toFloat($values[$ref]);
        }

        return $this->toFloat($ref);
    }

    private function toFloat(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v)) {
            $clean = str_replace([',', '٬', ' '], '', $v);
            $clean = strtr($clean, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

            return is_numeric($clean) ? (float) $clean : 0.0;
        }

        return 0.0;
    }

    private function band(float $value, array $bands): string
    {
        foreach ($bands as $band) {
            if ($value <= (float) ($band['max'] ?? PHP_FLOAT_MAX)) {
                return (string) ($band['label'] ?? '');
            }
        }

        return (string) (end($bands)['label'] ?? '');
    }

    public static function fmt(mixed $value): string
    {
        if (is_int($value)) {
            return number_format($value);
        }
        if (is_float($value)) {
            return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 1);
        }

        return (string) $value;
    }
}
