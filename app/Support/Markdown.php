<?php

namespace App\Support;

use App\Domain\Lab\Models\Tool;
use Illuminate\Support\Str;

/**
 * Tiny, safe Markdown subset for lesson bodies, assets, templates and magazine items:
 * headings (#..####, with ids for the table of contents), paragraphs, bold, links (http/https only),
 * unordered/ordered lists, pipe tables, blockquotes, horizontal rule, fenced code and the «طبّق الآن» box
 * (`:::apply <tool-slug>` … `:::`) that links to a lab tool. Input is HTML-escaped first, so nothing in the source can inject markup.
 */
class Markdown
{
    /** @var array<string, array{name:string,url:string}|null> */
    private static array $tools = [];

    /**
     * Lesson bodies were written with `#` as the section heading (rendered h2); magazine and glossary files use the
     * conventional `##` for h2, so they pass literalLevels: true (h1 is never emitted either way).
     */
    public static function toHtml(string $markdown, bool $headingIds = false, bool $literalLevels = false): string
    {
        $lines = preg_split('/\R/u', trim($markdown)) ?: [];
        $html = '';
        $i = 0;
        $n = count($lines);
        $usedIds = [];
        while ($i < $n) {
            $line = rtrim($lines[$i]);
            if ($line === '') {
                $i++;

                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.+)$/u', $line, $m)) {
                $level = $literalLevels ? max(2, min(4, strlen($m[1]))) : min(4, strlen($m[1]) + 1);
                $id = '';
                if ($headingIds && $level === 2) {
                    $id = ' id="'.e(self::headingId($m[2], $usedIds)).'"';
                }
                $html .= "<h{$level}{$id}>".self::inline($m[2])."</h{$level}>";
                $i++;

                continue;
            }
            if (preg_match('/^:::\s*apply(?:\s+([a-z0-9-]+))?\s*$/i', $line, $m)) {
                $body = [];
                $i++;
                while ($i < $n && ! preg_match('/^:::\s*$/', rtrim($lines[$i]))) {
                    $body[] = rtrim($lines[$i]);
                    $i++;
                }
                $i++;
                $html .= self::applyBox($m[1] ?? '', trim(implode(' ', array_filter($body, fn ($l) => $l !== ''))));

                continue;
            }
            if (str_starts_with($line, '```')) {
                $code = [];
                $i++;
                while ($i < $n && ! str_starts_with(rtrim($lines[$i]), '```')) {
                    $code[] = rtrim($lines[$i]);
                    $i++;
                }
                $i++;
                $html .= '<pre class="asset-md"><code>'.e(implode("\n", $code)).'</code></pre>';

                continue;
            }
            if (preg_match('/^(-{3,}|\*{3,})$/', $line)) {
                $html .= '<hr>';
                $i++;

                continue;
            }
            if (str_starts_with($line, '>')) {
                $quote = [];
                while ($i < $n && str_starts_with(rtrim($lines[$i]), '>')) {
                    $quote[] = trim(ltrim(rtrim($lines[$i]), '>'));
                    $i++;
                }
                $html .= '<blockquote><p>'.self::inline(implode(' ', array_filter($quote, fn ($l) => $l !== ''))).'</p></blockquote>';

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
            while ($i < $n && rtrim($lines[$i]) !== '' && ! preg_match('/^(#{1,4}\s|[-*]\s|\d+[.)]\s|\||>|:::|```)/u', rtrim($lines[$i]))) {
                $para[] = rtrim($lines[$i]);
                $i++;
            }
            if ($para === []) {
                $i++;

                continue;
            }
            $html .= '<p>'.self::inline(implode(' ', $para)).'</p>';
        }

        return $html;
    }

    /** H2 headings as [id => text], in document order (the sticky table of contents, §4.11). */
    public static function headings(string $markdown): array
    {
        $out = [];
        $used = [];
        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            if (preg_match('/^##\s+(.+)$/u', rtrim($line), $m)) {
                $out[self::headingId($m[1], $used)] = trim(strip_tags(self::inline($m[1])));
            }
        }

        return $out;
    }

    /** Word count of the readable text (no front matter, no directives). */
    public static function wordCount(string $markdown): int
    {
        $text = preg_replace('/^:::.*$/mu', '', $markdown) ?? $markdown;

        return str_word_count_utf8(strip_tags($text));
    }

    /**
     * Imported articles arrive as one paragraph per line with stray spaces and hard breaks inside sentences;
     * «نظّف الفقرات» merges broken lines, collapses whitespace and keeps real blank-line paragraph breaks.
     */
    public static function cleanParagraphs(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $blocks = preg_split('/\n\s*\n+/u', trim($text)) ?: [];
        $out = [];
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
            if ($lines === []) {
                continue;
            }
            // A block of short "lines" that are not list items or headings is one broken paragraph.
            $structural = array_filter($lines, fn ($l) => preg_match('/^(#{1,4}\s|[-*]\s|\d+[.)]\s|\||>|:::)/u', $l));
            if ($structural !== []) {
                $out[] = implode("\n", $lines);

                continue;
            }
            $merged = implode(' ', $lines);
            // Legacy imports often glue sentences; break very long paragraphs at sentence ends.
            if (mb_strlen($merged) > 900) {
                $sentences = preg_split('/(?<=[.!؟?])\s+/u', $merged) ?: [$merged];
                $chunk = '';
                foreach ($sentences as $s) {
                    if ($chunk !== '' && mb_strlen($chunk) + mb_strlen($s) > 600) {
                        $out[] = trim($chunk);
                        $chunk = '';
                    }
                    $chunk .= ($chunk === '' ? '' : ' ').$s;
                }
                if (trim($chunk) !== '') {
                    $out[] = trim($chunk);
                }

                continue;
            }
            $out[] = $merged;
        }

        return implode("\n\n", $out);
    }

    private static function headingId(string $text, array &$used): string
    {
        $base = Str::slug(trim(strip_tags($text)), '-', 'ar');
        if ($base === '' || preg_match('/^[\p{L}\p{N}-]+$/u', $base) !== 1) {
            $base = 'h-'.substr(md5($text), 0, 8);
        }
        // Str::slug with Arabic language keeps Arabic letters; strip anything else that is not url safe.
        $base = trim(preg_replace('/[^\p{L}\p{N}-]+/u', '-', $base) ?? $base, '-') ?: 'h-'.substr(md5($text), 0, 8);
        $id = $base;
        $k = 2;
        while (isset($used[$id])) {
            $id = $base.'-'.$k++;
        }
        $used[$id] = true;

        return $id;
    }

    private static function applyBox(string $slug, string $text): string
    {
        $tool = self::tool($slug);
        $html = '<aside class="apply-now" data-tool="'.e($slug).'"><span class="kicker">طبّق الآن</span>';
        if ($text !== '') {
            $html .= '<p>'.self::inline($text).'</p>';
        }
        if ($tool) {
            $html .= '<a class="btn btn-primary" href="'.e($tool['url']).'">افتح أداة «'.e($tool['name']).'»</a>';
        } else {
            $html .= '<a class="btn btn-primary" href="'.e(route('tools.index')).'">افتح المختبر</a>';
        }

        return $html.'</aside>';
    }

    /** Resolve a lab tool slug to its public page (generators, calculators and the simulator live on different routes). */
    public static function tool(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }
        if (! array_key_exists($slug, self::$tools)) {
            $tool = Tool::query()->where('slug', $slug)->where('is_published', true)->first();
            self::$tools[$slug] = $tool ? ['name' => $tool->name, 'url' => self::toolUrl($tool)] : null;
        }

        return self::$tools[$slug];
    }

    public static function toolUrl(Tool $tool): string
    {
        return match ($tool->kind) {
            'calculator' => route('tools.calculator', $tool->slug),
            'simulator' => route('tools.simulator'),
            default => route('tools.show', $tool),
        };
    }

    /** Tests and seeders swap tools in memory; forget the cache so links resolve against the current database. */
    public static function flushToolCache(): void
    {
        self::$tools = [];
    }

    private static function table(array $rows): string
    {
        $rows = array_values(array_filter($rows, fn ($r) => ! preg_match('/^:?-{2,}/', $r[0] ?? '')));
        if ($rows === []) {
            return '';
        }
        $head = array_shift($rows);
        $html = '<div class="table-wrap"><table><thead><tr>';
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

        return $html.'</tbody></table></div>';
    }

    private static function inline(string $text): string
    {
        $safe = e($text);
        $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe) ?? $safe;
        $safe = preg_replace('/`(.+?)`/u', '<code>$1</code>', $safe) ?? $safe;
        // [text](https://…) — only absolute http(s) links or site-relative paths; the href is already HTML-escaped by e().
        $safe = preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s)]+)\)/u', function ($m) {
            $external = str_starts_with($m[2], 'http');

            return '<a href="'.$m[2].'"'.($external ? ' rel="nofollow noopener" target="_blank"' : '').'>'.$m[1].'</a>';
        }, $safe) ?? $safe;

        return $safe;
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
