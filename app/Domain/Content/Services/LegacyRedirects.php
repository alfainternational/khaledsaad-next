<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\ContentItem;
use Illuminate\Support\Facades\DB;

/**
 * أين يذهب التحويل القديم فعلاً (runbook: domain-cutover).
 *
 * platform:import-legacy brings every legacy article in as a DRAFT on purpose — the platform's own policy is that
 * nothing publishes without a human reading it — and registers a 301 from /blog/{slug} to /articles/{slug} in the
 * same breath. Those two correct decisions produce one wrong result: on cutover day twenty indexed URLs answer a
 * permanent redirect into a 404, and nobody complains because nobody is there to complain.
 *
 * A 301 is also cached forever, so "just point them at the index" would be worse than the 404: the day the editor
 * finally publishes the article, every browser and crawler that saw the 301 keeps sending readers to the index
 * instead. So the destination is resolved at request time rather than rewritten in the table:
 *
 *   - target published  → the stored destination and the stored (permanent) status, untouched;
 *   - target not ready  → its section index with a 302, which caches nowhere and reverses itself.
 *
 * The row keeps saying what the editor meant. The day the article goes live the 301 resumes on its own, with no
 * data migration and nothing to remember.
 */
class LegacyRedirects
{
    /** Where the member actually lands, and with which status: [path, status]. */
    public static function resolve(string $target, int $status): array
    {
        $slug = self::articleSlug($target);
        if ($slug === null) {
            return [$target, $status];
        }

        $item = ContentItem::query()->where('slug', $slug)->first(['section', 'status', 'published_at']);
        if ($item && $item->isPublished()) {
            return [$target, $status];
        }

        return ['/'.self::section($item), 302];
    }

    /**
     * The slug an /articles/{slug} target points at, decoded; null for anything else.
     * A query string or fragment is dropped first: /articles/x?utm=y points at the same article as /articles/x,
     * and a target the check cannot read is a target that keeps its 301 into a 404.
     */
    public static function articleSlug(string $target): ?string
    {
        $path = (string) preg_replace('/[?#].*$/s', '', $target);
        if (preg_match('#^/articles/([^/]+)$#', $path, $matches) !== 1) {
            return null;
        }

        return rawurldecode($matches[1]);
    }

    /**
     * Active redirects whose article is not published yet: from_path => slug.
     * This is the cutover blocker in one query — platform:doctor reads it so it can never go quiet again.
     */
    public static function brokenTargets(): array
    {
        $slugs = [];
        $rows = DB::table('redirects')
            ->where('is_active', true)
            ->where('status_code', '!=', 410)
            ->where('to_path', 'like', '/articles/%')
            ->get(['from_path', 'to_path']);

        foreach ($rows as $row) {
            $slug = self::articleSlug((string) $row->to_path);
            if ($slug !== null) {
                $slugs[(string) $row->from_path] = $slug;
            }
        }
        if ($slugs === []) {
            return [];
        }

        $live = ContentItem::query()->published()->whereIn('slug', array_values($slugs))->pluck('slug')->all();

        return array_filter($slugs, static fn (string $slug): bool => ! in_array($slug, $live, true));
    }

    /**
     * The shelf an unready article belongs on. Whitelisted against SECTIONS rather than trusted from the column:
     * the resolved path goes straight into a Location header, and a section is only ever one of five known words.
     */
    private static function section(?ContentItem $item): string
    {
        $section = (string) ($item?->section ?? '');

        return array_key_exists($section, ContentItem::SECTIONS) ? $section : 'articles';
    }
}
