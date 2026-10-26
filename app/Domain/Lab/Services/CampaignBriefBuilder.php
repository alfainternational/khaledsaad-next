<?php

namespace App\Domain\Lab\Services;

use DomainException;

class CampaignBriefBuilder
{
    public function build(array $input): array
    {
        foreach (['goal', 'audience', 'offer'] as $field) {
            if (blank($input[$field] ?? null)) {
                throw new DomainException("الحقل {$field} مطلوب.");
            }
        }

        return [
            'goal' => trim($input['goal']),
            'audience' => trim($input['audience']),
            'offer' => trim($input['offer']),
            'message' => "نساعد {$input['audience']} على {$input['goal']} من خلال {$input['offer']}.",
            'experiment' => 'اختبر رسالة واحدة على شريحة واحدة لمدة سبعة أيام قبل توسيع الميزانية.',
            'measurement' => ['leading' => 'نسبة الاستجابة المؤهلة', 'outcome' => 'عدد الطلبات المؤهلة'],
            'review_note' => 'هذه مسودة أولية. راجع الادعاءات والأرقام وصوت العلامة قبل الاستخدام.',
        ];
    }
}
