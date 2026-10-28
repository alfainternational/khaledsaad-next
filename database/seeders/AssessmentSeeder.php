<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Assessment\Services\Catalog;
use Illuminate\Database\Seeder;

/**
 * تشخيص مشروعك: 8 أسئلة مواقف لكل جمهور تُصاغ من بيانات المشروع ({{budget}}, {{channel}}, {{offer}}, {{goal_number}})،
 * وقواعد توجيه كاملة: جمهور × مستوى × أضعف بُعد (2 × 3 × 5 = 30 قاعدة) ← درس بداية + خريطة 3 أولويات.
 */
class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        AssessmentQuestion::query()->delete();
        AssessmentRule::query()->delete();

        foreach ($this->questions('owner') as $i => $q) {
            AssessmentQuestion::query()->create($q + ['audience' => 'owner', 'position' => $i + 1, 'is_active' => true]);
        }
        foreach ($this->questions('marketer') as $i => $q) {
            AssessmentQuestion::query()->create($q + ['audience' => 'marketer', 'position' => $i + 1, 'is_active' => true]);
        }

        $lessons = Lesson::query()->pluck('id', 'slug');
        foreach (['owner', 'marketer'] as $audience) {
            foreach (array_keys(Catalog::LEVELS) as $level) {
                foreach (array_keys(Catalog::DIMENSIONS) as $dimension) {
                    [$slug, $priorities] = $this->rule($audience, $level, $dimension);
                    AssessmentRule::query()->create([
                        'audience' => $audience, 'level' => $level, 'weakest_dimension' => $dimension,
                        'lesson_id' => $lessons[$slug] ?? null, 'priorities' => $priorities,
                    ]);
                }
            }
        }
    }

    /** 8 situational questions; option weights 0–3 (no hidden scoring: the learner sees the dimension). */
    private function questions(string $audience): array
    {
        $you = $audience === 'owner' ? 'مشروعك' : 'مشروع عميلك';
        $w = fn (string $label, int $weight) => ['label' => $label, 'weight' => $weight];

        return [
            ['dimension' => 'goal', 'prompt' => "لو سألك شريك اليوم: «ما الرقم الذي تريد أن يصل إليه {$you} خلال 90 يوماً من {{goal}}؟» ماذا تجيب؟", 'options' => [
                $w('لا أملك رقماً، الهدف أن «نزيد المبيعات» عموماً', 0), $w('عندي رقم تقريبي في رأسي لكنه غير مكتوب ولا مرتبط بتاريخ', 1),
                $w('رقم مكتوب بتاريخ، لكن لا أعرف كيف أتابعه أسبوعياً', 2), $w('رقم مكتوب بتاريخ ومؤشر أسبوعي أراجعه فعلاً', 3),
            ]],
            ['dimension' => 'goal', 'prompt' => 'بميزانية {{budget}} شهرياً، كم طلباً أو عميلاً تتوقع أن تحصل عليه؟ وكيف وصلت إلى هذا الرقم؟', 'options' => [
                $w('لم أحسبها قط؛ أنفق وأرى ما يحدث', 0), $w('أقدّرها بالحدس من تجارب سابقة', 1),
                $w('أحسبها من تكلفة الطلب الواحد التي أعرفها تقريباً', 2), $w('أحسبها من تكلفة الطلب ومعدل التأهيل وأقارنها بطاقتي الفعلية', 3),
            ]],
            ['dimension' => 'audience', 'prompt' => 'لو اضطررت أن تصف العميل الذي يشتري «{{offer}}» لشخص سيصمّم لك إعلاناً، ماذا تعطيه؟', 'options' => [
                $w('وصفاً عاماً: الجميع يحتاجه تقريباً', 0), $w('فئة عمرية ومدينة فقط', 1),
                $w('شريحة واحدة بمشكلتها وبديلها الحالي', 2), $w('شريحة واحدة بمشكلتها وبديلها وأين تبحث عن الحل، مكتوبة ومستخدمة في كل رسالة', 3),
            ]],
            ['dimension' => 'audience', 'prompt' => 'آخر عشرة عملاء دفعوا فعلاً: هل تعرف ما الذي يجمعهم؟', 'options' => [
                $w('لا أتابع من يشتري ولا لماذا', 0), $w('أعرف بعض الأسماء لكن لم أبحث عن القاسم المشترك', 1),
                $w('لاحظت قاسماً مشتركاً ولم أستخدمه بعد في التسويق', 2), $w('أعرف القاسم المشترك وبنيت عليه الشريحة الأولى', 3),
            ]],
            ['dimension' => 'offer', 'prompt' => 'افتح آخر منشور أو صفحة تروّج لـ «{{offer}}». هل يفهم القارئ خلال 3 ثوانٍ: لمن هذا، وما الوعد، وما الخطوة التالية؟', 'options' => [
                $w('المحتوى عن المنتج وميزاته، بلا وعد ولا خطوة تالية', 0), $w('فيه وعد لكن بلا دليل ولا خطوة تالية واضحة', 1),
                $w('وعد ودليل وخطوة تالية، لكن الصياغة تتغير في كل مرة', 2), $w('رسالة أساسية ثابتة (وعد + دليل + خطوة) تُستخدم في كل القنوات', 3),
            ]],
            ['dimension' => 'channel', 'prompt' => 'قناتك الحالية هي {{channel}}. لماذا هذه القناة تحديداً؟', 'options' => [
                $w('لأنها الأسهل أو لأن الجميع يستخدمها', 0), $w('لأن منافسيّ عليها', 1),
                $w('لأن شريحتي موجودة فيها حسب ملاحظتي', 2), $w('اختبرت قناتين على الأقل بأرقام، وهذه أعطت أقل تكلفة للطلب', 3),
            ]],
            ['dimension' => 'channel', 'prompt' => 'لو بدأت حملة الشهر القادم بـ {{budget}}: متى تقرر إيقافها أو مضاعفتها؟', 'options' => [
                $w('عندما «أحس» أنها لا تنفع', 0), $w('عندما تنتهي الميزانية', 1),
                $w('عندما أرى تكلفة الطلب أعلى من المقبول، لكن بلا موعد محدد للقرار', 2), $w('قاعدة مكتوبة: تكلفة طلب مستهدفة وموعد قرار بعد 14 يوماً', 3),
            ]],
            ['dimension' => 'measure', 'prompt' => 'بعد أسبوعين من التنفيذ، كيف تعرف أن ما فعلته نفع في الوصول إلى {{goal_number}}؟', 'options' => [
                $w('من الإحساس العام وتعليقات الناس', 0), $w('من أرقام المنصة (إعجابات، وصول) فقط', 1),
                $w('من عدد الطلبات أو المحادثات، لكن بلا مقارنة بخط أساس', 2), $w('من مؤشر قيادي ومؤشر نتيجة مسجّلين أسبوعياً مقابل خط أساس', 3),
            ]],
        ];
    }

    /** [start lesson slug, priorities(3)] for audience × level × weakest dimension. */
    private function rule(string $audience, string $level, string $dimension): array
    {
        $you = $audience === 'owner' ? 'مشروعك' : 'مشروع عميلك';
        $owner = $audience === 'owner';
        $byDimension = [
            'goal' => ['what-marketing-really-is', [
                ['title' => 'ثبّت رقماً واحداً وتاريخاً لـ {{offer}}', 'reason' => "كل قرار تالٍ (الشريحة، الرسالة، القناة) يُقاس بهذا الرقم؛ بدونه لا يمكن الحكم على أي تجربة في {$you}.", 'lesson' => 'what-marketing-really-is'],
                ['title' => 'حوّل الرقم إلى خطة قناة واحدة بميزانية {{budget}}', 'reason' => 'الرقم يصبح قابلاً للتنفيذ عندما يُترجم إلى تكلفة طلب مقبولة وقاعدة إيقاف.', 'lesson' => 'first-channel-and-budget'],
                ['title' => 'ابنِ لوحة قياس أسبوعية من مؤشرين', 'reason' => 'الهدف الرقمي بلا متابعة أسبوعية يعود «إحساساً» خلال شهر.', 'lesson' => 'first-marketing-plan'],
            ]],
            'audience' => ['first-segment', [
                ['title' => 'اختر الشريحة الأولى لـ {{offer}}', 'reason' => 'الرسالة والقناة والسعر كلها تُبنى على شريحة واحدة واضحة؛ الاستهداف العام يُشتت الميزانية ({{budget}}) على من لن يشتري.', 'lesson' => 'first-segment'],
                ['title' => 'ارسم خريطة السوق حول هذه الشريحة', 'reason' => 'معرفة بديل الشريحة الحالي والفجوة التي لا يغطيها المنافسون تعطيك تموضعاً بدل منافسة السعر.', 'lesson' => 'market-map'],
                ['title' => 'اكتب الرسالة الأساسية للشريحة', 'reason' => 'بعد وضوح الشريحة تصبح الرسالة محددة: وعد + دليل + خطوة تالية.', 'lesson' => 'core-message'],
            ]],
            'offer' => ['core-message', [
                ['title' => 'اكتب رسالة أساسية واحدة لـ {{offer}}', 'reason' => 'الزيارات بلا طلبات غالباً مشكلة رسالة: القارئ لا يفهم لماذا أنت ولماذا الآن خلال ثلاث ثوانٍ.', 'lesson' => 'core-message'],
                ['title' => 'تحقق أن الرسالة تخاطب شريحة واحدة', 'reason' => 'الرسالة التي تحاول إقناع الجميع لا تقنع أحداً؛ راجع ملف الشريحة الأولى.', 'lesson' => 'first-segment'],
                ['title' => 'اختبر الرسالة على {{channel}} بميزانية صغيرة', 'reason' => 'الرسالة تُثبت نفسها بالاستجابة لا بالإعجاب الداخلي؛ أسبوعان على قناة واحدة كافيان.', 'lesson' => 'first-channel-and-budget'],
            ]],
            'channel' => ['first-channel-and-budget', [
                ['title' => 'ركّز {{budget}} على قناة واحدة بقاعدة إيقاف', 'reason' => 'توزيع الميزانية على قنوات عدة يُخفي ما ينفع؛ قناة واحدة بتكلفة طلب مستهدفة تعطي إجابة خلال 14 يوماً.', 'lesson' => 'first-channel-and-budget'],
                ['title' => 'تأكد أن الرسالة جاهزة قبل الإنفاق', 'reason' => 'أغلب هدر الإعلانات سببه رسالة غير واضحة لا قناة خاطئة.', 'lesson' => 'core-message'],
                ['title' => 'اجمع كل شيء في خطة 4 أسابيع', 'reason' => 'القناة والرسالة والشريحة تحتاج إيقاعاً أسبوعياً حتى لا تتحول إلى حملة واحدة ثم صمت.', 'lesson' => 'first-marketing-plan'],
            ]],
            'measure' => ['first-marketing-plan', [
                ['title' => 'سجّل مؤشرين أسبوعياً لـ {{offer}}: قيادي ونتيجة', 'reason' => 'ما لا يُقاس أسبوعياً لا يُحسَّن؛ مؤشران فقط كافيان لتعرف ما تُبقيه وما توقفه.', 'lesson' => 'first-marketing-plan'],
                ['title' => 'حدّد تكلفة الطلب المقبولة لقناتك ({{channel}})', 'reason' => 'الرقم المرجعي يحوّل «الإحساس» إلى قرار إيقاف أو مضاعفة.', 'lesson' => 'first-channel-and-budget'],
                ['title' => 'أعد التشخيص بعد المسار الأول', 'reason' => 'إعادة القياس بنفس الأسئلة تُظهر الأثر رقماً، لا انطباعاً.', 'course' => 'marketing-foundations'],
            ]],
        ];
        [$slug, $priorities] = $byDimension[$dimension];

        // Level shapes the framing: beginners get the fundamentals first, advanced learners get the measurement/scale angle.
        if ($level === 'beginner' && $dimension !== 'goal') {
            $priorities[2] = ['title' => 'ثبّت الأساس: ما التسويق فعلاً لمشروعك', 'reason' => 'بمستوى مبتدئ، تثبيت الهدف والقيمة قبل التوسع يوفّر عليك الهدر لاحقاً.', 'lesson' => 'what-marketing-really-is'];
        }
        if ($level === 'advanced') {
            $priorities[2] = ['title' => 'انتقل إلى مسار القياس والتحليلات وCRO', 'reason' => 'مستواك متقدم؛ الأثر الأكبر الآن في تحسين معدل التحويل وقراءة الأرقام لا في الأساسيات.', 'course' => 'measurement-analytics-cro'];
        }
        if (! $owner) {
            $priorities[1]['reason'] .= ' شارك الأصل مع العميل كرابط قراءة ليعتمده قبل التنفيذ.';
        }

        return [$slug, $priorities];
    }
}
