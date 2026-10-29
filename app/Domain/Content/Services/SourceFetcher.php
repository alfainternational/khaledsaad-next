<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\SourceFeed;
use App\Domain\Content\Models\SourceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * بوابة الالتقاط (blueprint §6.3): polls the watched RSS/Atom feeds with a short timeout, never fails the run when
 * the network is down (the error is recorded on the feed), scores relevance with Arabic/English keyword rules and
 * ignores recycled links automatically through url_hash.
 */
class SourceFetcher
{
    public const TIMEOUT_SECONDS = 8;

    public const MAX_ITEMS_PER_FEED = 15;

    /** Keyword rules: weight per hit (case-insensitive, Arabic and English). The score is capped at 100. */
    public const KEYWORDS = [
        // Core platform themes
        'marketing' => 8, 'تسويق' => 8, 'seo' => 8, 'search' => 4, 'google' => 4, 'ai' => 6, 'artificial intelligence' => 6, 'ذكاء اصطناعي' => 8,
        'content' => 5, 'محتوى' => 6, 'email' => 6, 'بريد' => 6, 'social' => 4, 'سوشيال' => 6, 'ads' => 6, 'advertising' => 6, 'إعلان' => 7,
        'campaign' => 5, 'حملة' => 6, 'conversion' => 7, 'تحويل' => 6, 'landing page' => 7, 'analytics' => 6, 'تحليلات' => 6,
        'attribution' => 6, 'customer' => 4, 'عميل' => 5, 'persona' => 7, 'segment' => 5, 'retention' => 6, 'churn' => 6,
        'cac' => 8, 'ltv' => 8, 'roas' => 8, 'roi' => 5, 'kpi' => 5, 'benchmark' => 7, 'report' => 5, 'study' => 6, 'survey' => 6, 'data' => 3,
        'small business' => 7, 'b2b' => 5, 'ecommerce' => 6, 'e-commerce' => 6, 'saudi' => 10, 'السعودية' => 10, 'arab' => 8, 'mena' => 8, 'عربي' => 8,
        'meta' => 3, 'tiktok' => 4, 'linkedin' => 4, 'whatsapp' => 5, 'youtube' => 3, 'instagram' => 3, 'chatgpt' => 5, 'llm' => 5, 'generative' => 5,
        'update' => 3, 'announce' => 3, 'launch' => 3, 'pricing' => 4, 'crm' => 6, 'automation' => 5, 'أتمتة' => 6, 'prompt' => 5,
    ];

    /** Items whose score falls below this are captured but marked low relevance (still visible; the editor decides). */
    public const RELEVANCE_FLOOR = 15;

    /** @return array{fetched:int, created:int, skipped:int, failed:int} */
    public function fetchAll(?callable $report = null): array
    {
        $totals = ['fetched' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (SourceFeed::query()->where('is_active', true)->orderBy('id')->get() as $feed) {
            $result = $this->fetch($feed);
            foreach ($totals as $k => $v) {
                $totals[$k] += $result[$k];
            }
            if ($report) {
                $report($feed, $result);
            }
        }

        return $totals;
    }

    /** @return array{fetched:int, created:int, skipped:int, failed:int, error:?string} */
    public function fetch(SourceFeed $feed): array
    {
        $result = ['fetched' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'error' => null];
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->connectTimeout(4)->withHeaders(['User-Agent' => 'AtharSourceBot/1.0 (+https://next.khaledsaad.net)'])
                ->retry(1, 200, throw: false)->get($feed->url);
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }
            $entries = $this->parse($response->body());
        } catch (Throwable $e) {
            $feed->forceFill(['last_fetched_at' => now(), 'last_error' => Str::limit($e->getMessage(), 180, '')])->save();
            $result['failed'] = 1;
            $result['error'] = $e->getMessage();

            return $result;
        }

        foreach (array_slice($entries, 0, self::MAX_ITEMS_PER_FEED) as $entry) {
            $result['fetched']++;
            $created = $this->capture($entry + ['publisher' => $feed->name, 'language' => $feed->language], $feed);
            $created ? $result['created']++ : $result['skipped']++;
        }
        $feed->forceFill(['last_fetched_at' => now(), 'last_error' => null, 'items_count' => $feed->items()->count()])->save();

        return $result;
    }

    /** Store one entry unless its url_hash already exists (recycled / duplicate links are ignored silently). */
    public function capture(array $entry, ?SourceFeed $feed = null, ?int $userId = null): ?SourceItem
    {
        $url = trim((string) ($entry['url'] ?? ''));
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $hash = SourceItem::hashUrl($url);
        if (SourceItem::query()->where('url_hash', $hash)->exists()) {
            return null;
        }
        $title = Str::limit(trim(html_entity_decode(strip_tags((string) ($entry['title'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 290, '…') ?: parse_url($url, PHP_URL_HOST);
        $excerpt = Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($entry['excerpt'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 1200, '…');
        [$score, $matched] = $this->score($title.' '.$excerpt);

        return SourceItem::query()->create([
            'source_feed_id' => $feed?->id,
            'title' => $title, 'url' => $url, 'url_hash' => $hash,
            'publisher' => $entry['publisher'] ?? ($feed?->name ?? parse_url($url, PHP_URL_HOST)),
            'language' => $entry['language'] ?? ($feed?->language ?? 'en'),
            'published_on' => $this->date($entry['published_on'] ?? null),
            'excerpt' => $excerpt ?: null,
            'relevance_score' => $score, 'matched_keywords' => $matched,
            'stage' => 'captured', 'added_by' => $userId,
        ]);
    }

    /** @return array{0:int,1:array<int,string>} */
    public function score(string $text): array
    {
        $haystack = mb_strtolower($text);
        $score = 0;
        $matched = [];
        foreach (self::KEYWORDS as $keyword => $weight) {
            $pattern = preg_match('/^[a-z0-9]/i', $keyword) ? '/\b'.preg_quote($keyword, '/').'\b/u' : '/'.preg_quote($keyword, '/').'/u';
            if (preg_match($pattern, $haystack)) {
                $score += $weight;
                $matched[] = $keyword;
            }
        }

        return [min(100, $score), $matched];
    }

    /** RSS 2.0 and Atom, defensively (no external entities, malformed XML returns no entries). */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($doc === false) {
            return [];
        }
        $entries = [];
        if (isset($doc->channel->item)) {
            foreach ($doc->channel->item as $item) {
                $entries[] = [
                    'title' => (string) $item->title,
                    'url' => trim((string) ($item->link ?: $item->guid)),
                    'excerpt' => (string) ($item->description ?: $item->children('content', true)->encoded ?? ''),
                    'published_on' => (string) ($item->pubDate ?: $item->children('dc', true)->date ?? ''),
                ];
            }
        } elseif (isset($doc->entry)) {
            foreach ($doc->entry as $entry) {
                $href = '';
                foreach ($entry->link as $link) {
                    $rel = (string) ($link['rel'] ?? 'alternate');
                    if ($rel === 'alternate' || $href === '') {
                        $href = (string) $link['href'];
                    }
                }
                $entries[] = [
                    'title' => (string) $entry->title,
                    'url' => trim($href),
                    'excerpt' => (string) ($entry->summary ?: $entry->content),
                    'published_on' => (string) ($entry->published ?: $entry->updated),
                ];
            }
        }

        return array_values(array_filter($entries, fn ($e) => $e['url'] !== ''));
    }

    private function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
