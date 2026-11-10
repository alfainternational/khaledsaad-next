<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Ai\AiContext;
use App\Support\Web\FetchedPage;
use App\Support\Web\WebFetcher;
use DomainException;
use Illuminate\Support\Str;
use Throwable;

/**
 * اقرأ الواقع (spec 2026-11-09 §5، الموجة 1): رابط موقع العميل ← ملف علامة يعبّئ المولّدات التسعة.
 *
 * The platform's biggest gap was that it asked a member to describe their own business in a dozen fields before it
 * could help them — while that description already exists, written by them, on their own website. This reads it.
 *
 * Two deliberate choices:
 *
 *  1. **It works with no AI at all.** The deterministic pass reads the title, the description, the headings, the
 *     prices and the contact channels, which is most of what the profile fields need. A model then sharpens the
 *     wording. Same contract as every generator here: the base output never depends on a key or a credit.
 *  2. **It proposes; it does not overwrite.** The project profile is the member's own words and the hub all nine
 *     generators prefill from. Replacing it from a crawl without review would be the platform talking over them.
 */
class BrandReader
{
    /** Fields of the project profile this reader can propose, in the order the review screen shows them. */
    public const FIELDS = ['offer', 'audience', 'sector', 'differentiator', 'proof', 'channel'];

    public function __construct(
        private readonly WebFetcher $web,
        private readonly AiGateway $ai,
    ) {}

    public function aiEnabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * @return array{page: FetchedPage, facts: array, proposal: array, used_ai: bool, notice: ?string}
     *
     * @throws DomainException when the URL itself is refused (guard, robots, rate) — a message for the member
     */
    public function read(string $url, ?AiContext $context = null): array
    {
        $page = $this->web->text($url);
        if (! $page->ok()) {
            throw new DomainException('تعذّر الوصول إلى الصفحة. تأكد من الرابط، أو جرّب صفحة «من نحن» بدل الصفحة الرئيسية.');
        }

        $facts = $this->facts($page);
        if (($facts['words'] ?? 0) < 40) {
            throw new DomainException('الصفحة تكاد تكون بلا نصّ مقروء (قد تكون مبنية بالكامل بجافاسكربت). جرّب صفحة «من نحن» أو صفحة منتج.');
        }

        $proposal = $this->deterministicProposal($facts);
        $usedAi = false;
        $notice = null;

        if ($this->ai->enabled()) {
            try {
                $proposal = array_merge($proposal, $this->aiProposal($facts, $page, $context));
                $usedAi = true;
            } catch (DomainException $exception) {
                $notice = $exception->getMessage().' عُرض الاستخلاص القاعدي من الصفحة نفسها.';
            } catch (Throwable $exception) {
                report($exception);
                $notice = 'تعذّر تحسين الاستخلاص بالذكاء الاصطناعي؛ عُرض الاستخلاص القاعدي من الصفحة نفسها.';
            }
        } else {
            $notice = 'الذكاء الاصطناعي غير مفعّل؛ هذا استخلاص قاعدي من نصّ الصفحة بلا خصم رصيد.';
        }

        return ['page' => $page, 'facts' => $facts, 'proposal' => $proposal, 'used_ai' => $usedAi, 'notice' => $notice];
    }

    /** What the page says about itself, without interpretation — this is what the member can check against. */
    public function facts(FetchedPage $page): array
    {
        $text = $page->text(12000);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));

        $headings = [];
        foreach ($lines as $line) {
            if (preg_match('/^#{1,3}\s+(.+)$/u', $line, $m)) {
                $headings[] = trim($m[1]);
            }
        }
        $headings = array_values(array_unique($headings));

        // The first paragraph of real prose: the shortest honest answer to "what is this".
        $lead = '';
        foreach ($lines as $line) {
            if (! str_starts_with($line, '#') && mb_strlen($line) >= 60) {
                $lead = $line;
                break;
            }
        }

        $arabicChars = preg_match_all('/\p{Arabic}/u', $text) ?: 0;
        $words = str_word_count(strip_tags($text), 0, 'ابتثجحخدذرزسشصضطظعغفقكلمنهويءآأؤإئةى') ?: count(preg_split('/\s+/u', $text) ?: []);

        return [
            'url' => $page->url,
            'host' => $page->host(),
            'title' => $page->title ?? '',
            'lead' => $lead,
            'headings' => array_slice($headings, 0, 12),
            'words' => $words,
            'is_arabic' => $arabicChars > ($words * 0.3),
            'prices' => $this->prices($text),
            'channels' => $this->channels($text),
            'via' => $page->via,
        ];
    }

    /** @return array<int, string> */
    private function prices(string $text): array
    {
        preg_match_all('/(\d[\d,.]{1,9})\s*(ريال|ر\.س|SAR|USD|\$|درهم|AED)/iu', $text, $m, PREG_SET_ORDER);
        $out = [];
        foreach (array_slice($m, 0, 6) as $hit) {
            $out[] = trim($hit[0]);
        }

        return array_values(array_unique($out));
    }

    /** @return array<int, string> */
    private function channels(string $text): array
    {
        $found = [];
        foreach ([
            'whatsapp' => '/wa\.me|whatsapp|واتساب/iu',
            'instagram' => '/instagram\.com|إنستغرام|انستقرام/iu',
            'tiktok' => '/tiktok\.com|تيك ?توك/iu',
            'snapchat' => '/snapchat\.com|سناب/iu',
            'google' => '/g\.page|maps\.app\.goo|خرائط ?جوجل/iu',
        ] as $channel => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = $channel;
            }
        }

        return $found;
    }

    /** The no-AI proposal: what can be asserted from the page without a model inventing anything. */
    private function deterministicProposal(array $facts): array
    {
        $title = trim((string) $facts['title']);
        $brand = $title !== '' ? Str::limit(preg_split('/[|\-–—]/u', $title)[0] ?? $title, 60, '') : $facts['host'];

        return [
            'brand' => trim($brand),
            'offer' => Str::limit($facts['lead'] !== '' ? $facts['lead'] : $title, 180, '…'),
            'audience' => '',
            'sector' => $this->guessSector($facts),
            'differentiator' => '',
            'proof' => $facts['prices'] !== [] ? 'أسعار معلنة على الموقع: '.implode('، ', array_slice($facts['prices'], 0, 3)) : '',
            'channel' => $facts['channels'][0] ?? '',
            'summary' => '',
        ];
    }

    /** A sector from the catalogue, matched on words the page itself uses; empty when nothing matches honestly. */
    private function guessSector(array $facts): string
    {
        $haystack = mb_strtolower($facts['title'].' '.$facts['lead'].' '.implode(' ', $facts['headings']));
        $rules = [
            'beauty' => ['عناية', 'بشرة', 'تجميل', 'سيروم', 'عطر', 'skincare', 'beauty', 'cosmetic'],
            'food' => ['مطعم', 'قهوة', 'كافيه', 'حلويات', 'مخبز', 'طعام', 'coffee', 'restaurant', 'bakery'],
            'services' => ['استشار', 'خدمات', 'محاسب', 'محام', 'تصميم', 'صيانة', 'consult', 'agency', 'services'],
            'retail' => ['متجر', 'تسوق', 'منتجات', 'shop', 'store', 'ecommerce'],
            'education' => ['دورة', 'تدريب', 'أكاديمية', 'تعليم', 'course', 'academy', 'training'],
            'health' => ['عيادة', 'طبي', 'أسنان', 'صحة', 'clinic', 'dental', 'medical'],
            'realestate' => ['عقار', 'شقق', 'فلل', 'إيجار', 'real estate', 'property'],
        ];
        foreach ($rules as $sector => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, mb_strtolower($word))) {
                    return isset(Catalog::SECTORS[$sector]) ? $sector : '';
                }
            }
        }

        return '';
    }

    /**
     * One call, strict JSON, and the prompt forbids inventing: a profile field the page does not support must come
     * back empty rather than plausible. A fabricated audience is worse than a blank one — the member would keep it.
     */
    private function aiProposal(array $facts, FetchedPage $page, ?AiContext $context): array
    {
        $instructions = <<<'TXT'
            أنت تستخلص ملف علامة من نصّ صفحة ويب. لا تخمّن ولا تضف ما لا تسنده الصفحة.
            أجب بـ JSON فقط بهذه المفاتيح: brand, offer, audience, sector, differentiator, proof, summary.
            - brand: اسم النشاط كما يظهر في الصفحة.
            - offer: ما الذي يبيعه، جملة واحدة بلغة الصفحة.
            - audience: لمن، جملة واحدة. اتركها "" إن لم تذكر الصفحة جمهوراً.
            - sector: واحد من: beauty, food, services, retail, education, health, realestate — أو "".
            - differentiator: ما يميّزه كما تقوله الصفحة. "" إن لم يُذكر.
            - proof: دليل ملموس ورد في الصفحة (أرقام، شهادات، سنوات خبرة). "" إن لم يُذكر.
            - summary: ثلاث جمل تصف النشاط لمن لم يزر الموقع.
            لا تخترع أرقاماً ولا جمهوراً ولا ادعاءات. لا تكتب نصاً خارج JSON.
            TXT;

        $prompt = "العنوان: {$facts['title']}\nالنطاق: {$facts['host']}\n";
        if ($facts['headings'] !== []) {
            $prompt .= 'العناوين: '.implode(' · ', array_slice($facts['headings'], 0, 10))."\n";
        }
        if ($facts['prices'] !== []) {
            $prompt .= 'أسعار وردت: '.implode('، ', $facts['prices'])."\n";
        }
        $prompt .= "\nنصّ الصفحة:\n\"\"\"\n".$page->text(6000)."\n\"\"\"\n";

        $answer = $this->ai->generate($prompt, $instructions, $context ?? AiContext::internal('reader:brand-profile'));
        $decoded = $this->decode($answer['text']);

        $allowed = ['brand', 'offer', 'audience', 'sector', 'differentiator', 'proof', 'summary'];
        $out = [];
        foreach ($allowed as $key) {
            $value = $decoded[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$key] = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
            }
        }
        if (isset($out['sector']) && ! isset(Catalog::SECTORS[$out['sector']])) {
            unset($out['sector']);
        }

        return $out;
    }

    private function decode(string $text): array
    {
        $body = trim(preg_replace('/^```(?:json)?|```$/mu', '', trim($text)) ?? $text);
        $start = strpos($body, '{');
        $end = strrpos($body, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $decoded = json_decode(substr($body, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }
}
