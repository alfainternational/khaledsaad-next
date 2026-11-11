<?php

namespace App\Domain\Assessment\Services;

/**
 * العبارات التي تحمل حرف جرّها — كي لا يفترض أي قالب وجود ما قد لا يكون موجوداً.
 *
 * The project vocabulary contains answers whose MEANING is "there is none yet": channel `none`, budget `0`,
 * sector `other`. They are valid, deliberately chosen answers, not missing ones — which is exactly why three
 * separate value bags (the diagnosis, the lesson composer, the generator) each rendered them as though they
 * named a thing, and produced sentences like «عبر لا قناة ثابتة بعد» and «بميزانية بلا ميزانية إعلانية».
 *
 * A label is a word for a list or a badge. A sentence needs a phrase, and in Arabic the preposition changes with
 * the case — «عبر إنستغرام» but «بلا قناة ثابتة بعد» — so no amount of swapping a single word inside a fixed
 * sentence can be made correct. The preposition has to travel with the value, which is what these phrases are.
 *
 * Templates use `{{channel_phrase}}`, never `عبر {{channel_label}}`. The labels stay for lists and tables.
 */
class ProjectPhrases
{
    /** The key in each vocabulary that means "none yet". Declared once so nothing has to recognise it by its text. */
    public const NONE = ['channel' => 'none', 'sector' => 'other'];

    public static function isNone(string $field, mixed $value): bool
    {
        return match ($field) {
            'budget', 'goal_number' => (int) $value <= 0,
            default => ! isset(self::NONE[$field]) ? ($value === null || $value === '') : ($value === null || $value === '' || (string) $value === self::NONE[$field]),
        };
    }

    /**
     * Every sentence-shaped placeholder, from a raw project profile.
     * Each value is a complete phrase: it reads correctly on its own and needs no preposition in front of it.
     */
    public static function all(array $profile): array
    {
        $channel = $profile['channel'] ?? null;
        $sector = $profile['sector'] ?? null;
        $budget = (int) ($profile['budget'] ?? 0);
        $goalNumber = (int) ($profile['goal_number'] ?? 0);
        $goalLabel = Catalog::label(Catalog::GOALS, $profile['goal'] ?? null, 'نتيجة واضحة');

        return [
            'channel_phrase' => self::isNone('channel', $channel)
                ? 'بلا قناة ثابتة بعد'
                : 'عبر '.Catalog::label(Catalog::CHANNELS, (string) $channel),

            // "قناته الحالية X" has no honest short form when there is no channel, so the clause changes shape.
            'channel_clause' => self::isNone('channel', $channel)
                ? 'ولم يثبّت قناة بعد'
                : 'وقناته الحالية '.Catalog::label(Catalog::CHANNELS, (string) $channel),

            'budget_phrase' => $budget > 0 ? 'بميزانية '.number_format($budget).' ريال' : 'بلا ميزانية إعلانية',

            // The sector travels WITH the offer rather than in a slot of its own, because the honest rendering of an
            // unknown sector is no clause at all — and an empty placeholder is not nothing: Markdown::fill writes «—»
            // for it, which is right in a table cell and absurd in a sentence («يبيع سيروم عناية —»).
            'offer_phrase' => trim(($profile['offer'] ?? 'ما يبيعه').(self::isNone('sector', $sector)
                ? ''
                : ' في قطاع '.Catalog::label(Catalog::SECTORS, (string) $sector))),

            'sector_phrase' => self::isNone('sector', $sector) ? 'قطاع غير محدد' : Catalog::label(Catalog::SECTORS, (string) $sector),

            // No invented number, and no apology for its absence either: «يحتاج طلبات مؤهلة شهرياً» is true and reads
            // as Arabic. That the member has set no target is the goal dimension's finding to report, not this sentence's.
            'goal_phrase' => $goalNumber > 0 ? number_format($goalNumber).' '.$goalLabel : $goalLabel,
        ];
    }
}
