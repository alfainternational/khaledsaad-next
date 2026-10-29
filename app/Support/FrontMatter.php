<?php

namespace App\Support;

/**
 * Minimal front-matter reader for database/content/*.md: scalars (`key: value`), inline lists (`[a, b]`),
 * block lists of scalars (`- value`) and block lists of maps (`- key: value` + indented `key: value` lines).
 * Values may be wrapped in single or double quotes. No anchors, no multi-line scalars: content files stay plain.
 */
class FrontMatter
{
    /** @return array{0: array<string, mixed>, 1: string} [attributes, body] */
    public static function parse(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", ltrim($raw, "\xEF\xBB\xBF"));
        if (! preg_match('/^---\n(.*?)\n---\n?(.*)$/su', $raw, $m)) {
            return [[], trim($raw)];
        }

        return [self::attributes($m[1]), trim($m[2])];
    }

    public static function attributes(string $block): array
    {
        $data = [];
        $key = null;        // current top-level key for block lists
        $entry = null;      // current list-of-maps entry
        foreach (explode("\n", $block) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            $trim = trim($line);

            if ($indent === 0 && preg_match('/^([a-z_][a-z0-9_]*):\s?(.*)$/u', $trim, $m)) {
                self::flush($data, $key, $entry);
                $key = $m[1];
                $value = trim($m[2]);
                if ($value === '') {
                    $data[$key] = [];
                } else {
                    $data[$key] = self::scalar($value);
                    $key = null;
                }

                continue;
            }
            if ($key === null) {
                continue;
            }
            if (preg_match('/^-\s*(.*)$/u', $trim, $m)) {
                if ($entry !== null) {
                    $data[$key][] = $entry;
                    $entry = null;
                }
                $item = trim($m[1]);
                if (preg_match('/^([a-z_][a-z0-9_]*):\s?(.*)$/u', $item, $kv)) {
                    $entry = [$kv[1] => self::scalar(trim($kv[2]))];
                } elseif ($item !== '') {
                    $data[$key][] = self::scalar($item);
                }

                continue;
            }
            if ($entry !== null && preg_match('/^([a-z_][a-z0-9_]*):\s?(.*)$/u', $trim, $kv)) {
                $entry[$kv[1]] = self::scalar(trim($kv[2]));
            }
        }
        self::flush($data, $key, $entry);

        return $data;
    }

    private static function flush(array &$data, ?string &$key, ?array &$entry): void
    {
        if ($key !== null && $entry !== null) {
            $data[$key][] = $entry;
        }
        $entry = null;
    }

    private static function scalar(string $value): mixed
    {
        if ($value === '[]') {
            return [];
        }
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return array_values(array_filter(array_map(fn ($v) => self::unquote(trim($v)), explode(',', substr($value, 1, -1))), fn ($v) => $v !== ''));
        }
        $value = self::unquote($value);
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        return $value;
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
