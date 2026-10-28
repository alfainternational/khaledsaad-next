<?php

namespace App\Support;

/**
 * Tiny, safe Markdown subset for lesson bodies, assets and templates:
 * headings (#..####), paragraphs, bold, unordered/ordered lists, pipe tables, horizontal rule.
 * Input is HTML-escaped first, so nothing in the source can inject markup.
 */
class Markdown
{
    public static function toHtml(string $markdown): string
    {
        $lines = preg_split('/\R/u', trim($markdown)) ?: [];
        $html = '';
        $i = 0;
        $n = count($lines);
        while ($i < $n) {
            $line = rtrim($lines[$i]);
            if ($line === '') {
                $i++;

                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.+)$/u', $line, $m)) {
                $level = min(4, strlen($m[1]) + 1);
                $html .= "<h{$level}>".self::inline($m[2])."</h{$level}>";
                $i++;

                continue;
            }
            if (preg_match('/^(-{3,}|\*{3,})$/', $line)) {
                $html .= '<hr>';
                $i++;

                continue;
            }
            if (str_starts_with($line, '|')) {
                $rows = [];
                while ($i < $n && str_starts_with(rtrim($lines[$i]), '|')) {
                    $rows[] = array_map('trim', explode('|', trim(rtrim($lines[$i]), '|')));
                    $i++;
                }
                $html .= self::table($rows);

                continue;
            }
            if (preg_match('/^[-*]\s+/u', $line)) {
                $html .= '<ul>';
                while ($i < $n && preg_match('/^[-*]\s+(.*)$/u', rtrim($lines[$i]), $m)) {
                    $html .= '<li>'.self::inline($m[1]).'</li>';
                    $i++;
                }
                $html .= '</ul>';

                continue;
            }
            if (preg_match('/^\d+[.)]\s+/u', $line)) {
                $html .= '<ol>';
                while ($i < $n && preg_match('/^\d+[.)]\s+(.*)$/u', rtrim($lines[$i]), $m)) {
                    $html .= '<li>'.self::inline($m[1]).'</li>';
                    $i++;
                }
                $html .= '</ol>';

                continue;
            }
            $para = [];
            while ($i < $n && rtrim($lines[$i]) !== '' && ! preg_match('/^(#{1,4}\s|[-*]\s|\d+[.)]\s|\|)/u', rtrim($lines[$i]))) {
                $para[] = rtrim($lines[$i]);
                $i++;
            }
            $html .= '<p>'.self::inline(implode(' ', $para)).'</p>';
        }

        return $html;
    }

    private static function table(array $rows): string
    {
        $rows = array_values(array_filter($rows, fn ($r) => ! preg_match('/^:?-{2,}/', $r[0] ?? '')));
        if ($rows === []) {
            return '';
        }
        $head = array_shift($rows);
        $html = '<table><thead><tr>';
        foreach ($head as $cell) {
            $html .= '<th>'.self::inline($cell).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.self::inline($cell).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    private static function inline(string $text): string
    {
        $safe = e($text);
        $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe) ?? $safe;

        return preg_replace('/`(.+?)`/u', '<code>$1</code>', $safe) ?? $safe;
    }

    /** Fill {{key}} placeholders; unknown keys become "—". */
    public static function fill(string $template, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function ($m) use ($values) {
            $value = data_get($values, $m[1]);
            if (is_array($value)) {
                $value = implode('، ', array_filter($value, 'is_scalar'));
            }

            return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : '—';
        }, $template) ?? $template;
    }
}
