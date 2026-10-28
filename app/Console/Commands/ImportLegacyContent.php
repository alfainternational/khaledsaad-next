<?php

namespace App\Console\Commands;

use App\Domain\Content\Models\ContentItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pull published public articles from the legacy khaledsaad.net database (connection "legacy",
 * table "contents") into content_items as drafts, preserving slugs, and register the
 * /blog/{slug} -> /articles/{slug} redirect for each one. Re-runnable: matches by slug.
 */
class ImportLegacyContent extends Command
{
    protected $signature = 'platform:import-legacy
        {--types= : Comma-separated legacy content types to import (default: all types)}
        {--publish : Publish imported items immediately (keeps the legacy publish date)}
        {--limit=0 : Import at most N rows (0 = all)}
        {--dry-run : Report what would be imported without writing}';

    protected $description = 'Import legacy blog articles into content_items (as drafts) and create their redirects';

    public function handle(): int
    {
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('types')))));
        $query = DB::connection('legacy')->table('contents')
            ->when($types !== [], fn ($q) => $q->whereIn('type', $types))
            ->where('status', 'published')
            ->where('access_level', 'public')
            ->orderBy('published_at');
        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }
        $rows = $query->get();
        $categories = DB::connection('legacy')->table('content_categories')->pluck('name', 'id');

        $created = $updated = 0;
        foreach ($rows as $row) {
            $text = self::toPlainParagraphs($row->body_html ?? null, $row->body_json ?? null);
            if ($text === '') {
                $this->warn("skip {$row->slug}: empty body");

                continue;
            }
            $excerpt = trim((string) ($row->excerpt ?? '')) ?: Str::limit($text, 220, '…');
            $minutes = (int) ($row->duration_minutes ?? 0) ?: max(2, (int) ceil(mb_strlen($text) / 900));
            $topic = $row->category_id ? ($categories[$row->category_id] ?? null) : null;
            $publishedAt = $row->published_at ?: $row->created_at;

            $attributes = [
                'type' => 'article',
                'title' => $row->title,
                'eyebrow' => $topic ?: 'من الأرشيف',
                'excerpt' => $excerpt,
                'body' => $text,
                'author_name' => 'خالد سعد',
                'topic' => $topic,
                'source_mode' => 'legacy_import',
                'reading_minutes' => min(120, $minutes),
                'meta' => [
                    'legacy' => [
                        'id' => $row->id,
                        'type' => $row->type,
                        'cover_image_path' => $row->cover_image_path ?? null,
                        'seo_title' => $row->seo_title ?? null,
                        'seo_description' => $row->seo_description ?? null,
                        'body_html' => $row->body_html ?? null,
                        'published_at' => $publishedAt,
                    ],
                ],
            ];
            if ($this->option('publish')) {
                $attributes['status'] = 'published';
                $attributes['published_at'] = $publishedAt;
            }

            $existing = ContentItem::query()->where('slug', $row->slug)->first();
            $this->line(($existing ? 'update ' : 'create ')."{$row->slug}");
            if ($this->option('dry-run')) {
                continue;
            }

            if ($existing) {
                // Never downgrade something an editor already published.
                if ($existing->status === 'published') {
                    unset($attributes['status'], $attributes['published_at']);
                }
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                ContentItem::query()->create($attributes + ['slug' => $row->slug, 'status' => 'draft']);
                $created++;
            }

            DB::table('redirects')->updateOrInsert(
                ['from_path' => '/blog/'.rawurldecode($row->slug)],
                ['to_path' => '/articles/'.$row->slug, 'status_code' => 301, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->info("Legacy import: {$created} created, {$updated} updated, ".count($rows).' candidate(s).');

        return self::SUCCESS;
    }

    /** Flatten legacy HTML (or an editor JSON blob) into blank-line separated plain paragraphs. */
    public static function toPlainParagraphs(?string $html, ?string $json): string
    {
        if (blank($html) && filled($json)) {
            $decoded = json_decode($json, true);
            $html = is_array($decoded) ? implode("\n", self::stringsIn($decoded)) : '';
        }
        $html = (string) $html;
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/h[1-6]|/li|/blockquote|/div|/tr)[^>]*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_filter(array_map(fn ($l) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $l) ?? $l), preg_split('/\R/u', $text) ?: []));

        return implode("\n\n", $lines);
    }

    private static function stringsIn(array $data): array
    {
        $out = [];
        array_walk_recursive($data, function ($value, $key) use (&$out): void {
            if (is_string($value) && in_array($key, ['text', 'content', 'caption', 'title', 'html'], true) && trim($value) !== '') {
                $out[] = $value;
            }
        });

        return $out;
    }
}
