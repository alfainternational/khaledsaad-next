<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\SourceItem;
use App\Domain\Lab\Services\AiGateway;
use DomainException;
use Illuminate\Support\Str;

/**
 * بوابة الملخص (blueprint §6.3): raw material for the writer, never published as is.
 * Rules first (title + excerpt + verification facts); AI through AiGateway when a key exists, with the same rule output as fallback.
 */
class SourceSummarizer
{
    public function __construct(private readonly AiGateway $ai) {}

    public static function aiEnabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    /** @return array{text:string, mode:string} */
    public function summarize(SourceItem $item, bool $useAi = true): array
    {
        $rules = $this->byRules($item);
        if (! $useAi || ! self::aiEnabled()) {
            return ['text' => $rules, 'mode' => 'rules'];
        }
        try {
            $out = $this->ai->generate(
                "العنوان: {$item->title}\nالناشر: {$item->publisherName()}\nالتاريخ: ".($item->published_on?->toDateString() ?? 'غير معروف')."\nالمقتطف: ".Str::limit((string) $item->excerpt, 1500, '')."\n\nاكتب ملخصاً عربياً خاماً للمحرر في 5 نقاط: ما الادعاء الرئيسي، ما الأرقام المذكورة ومصدرها الأولي إن وُجد، من يتأثر، ما الذي لا يقوله المصدر، وما السؤال الذي يجب أن تجيب عنه المادة العربية. لا تخترع أرقاماً.",
                'أنت محرر عربي دقيق. المخرج مادة خام للكاتب ولن يُنشر كما هو. افصل ما ورد في المصدر عن استنتاجك، ولا تضف أرقاماً غير موجودة في المدخلات.'
            );

            return ['text' => $out['text']."\n\n— ملخص آلي (AI) للمراجعة؛ لا يُنشر كما هو.", 'mode' => 'ai'];
        } catch (DomainException) {
            return ['text' => $rules, 'mode' => 'rules'];
        }
    }

    public function byRules(SourceItem $item): string
    {
        $excerpt = trim((string) $item->excerpt);
        $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?؟])\s+/u', $excerpt) ?: []), fn ($s) => mb_strlen($s) > 20));
        $numbers = [];
        if (preg_match_all('/\d+(?:[.,]\d+)?\s?(?:%|percent|٪|million|billion|k\b)/iu', $excerpt.' '.$item->title, $m)) {
            $numbers = array_values(array_unique($m[0]));
        }
        $verification = $item->verification ?? [];
        $lines = [
            '**الادعاء الرئيسي (من العنوان):** '.$item->title,
            '**ما يقوله المقتطف:** '.($sentences === [] ? 'لا مقتطف كافياً؛ افتح الرابط الأصلي.' : implode(' ', array_slice($sentences, 0, 3))),
            '**الأرقام الواردة:** '.($numbers === [] ? 'لا أرقام في المقتطف. أي رقم يُضاف لاحقاً يحتاج مصدراً أولياً.' : implode('، ', $numbers).' — مصدرها الأولي: '.($verification['primary_source'] ?? 'لم يُحدَّد بعد')),
            '**الناشر والتاريخ:** '.$item->publisherName().' · '.($item->published_on?->format('Y-m-d') ?? 'بلا تاريخ').' · '.strtoupper($item->language),
            '**ما لا يقوله المصدر:** السوق العربي غير مذكور؛ انطباق النتائج محلياً يحتاج تبريراً في «السياق العربي».'.(($verification['market_fit'] ?? null) ? ' ملاحظة التحقق: '.$verification['market_fit'] : ''),
            '**السؤال الذي تجيب عنه المادة العربية:** ماذا يغيّر هذا في قرار صاحب مشروع صغير هذا الأسبوع؟',
            '',
            '— ملخص آلي بالقواعد؛ مادة خام للكاتب لا تُنشر كما هي.',
        ];

        return implode("\n", $lines);
    }
}
