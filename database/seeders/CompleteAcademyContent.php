<?php

namespace Database\Seeders;

/** Per-course editorial sources; the shared builder only supplies the runtime contract. */
class CompleteAcademyContent
{
    public static function courses(): array
    {
        $courses = [];
        foreach (array_slice(AcademySeeder::COURSES, 1) as [$slug]) {
            $source = require database_path('content/academy/'.$slug.'.php');
            $needs = $source['needs'] ?? ['marketing_plan' => 'خطة التسويق الأولى'];
            $lessons = [];
            foreach ($source['lessons'] as $index => $row) {
                $lesson = self::lesson($source, $row, $needs, $index);
                $lessons[] = $lesson;
                $needs[$lesson['outcome_type']] = $lesson['outcome_title'];
            }
            $courses[$slug] = ['module' => $source['module'], 'lessons' => $lessons];
        }

        return $courses;
    }

    private static function lesson(array $course, array $row, array $needs, int $index): array
    {
        $guided = [];
        $sections = [];
        $practice = [];
        foreach ($row['fields'] as $key => [$label, $hint, $example]) {
            $guided[] = ['key' => $key, 'label' => $label, 'hint' => $hint, 'placeholder' => $example, 'required' => true, 'source' => $row['sources'][$key] ?? null];
            $sections[] = '## '.$label."\n\n{{".$key.'}}';
            $practice[] = '**'.$label.'**: '.$hint.' مثال صياغة للعمل على الحالة التدريبية: «'.$example.'». استخدم ملاحظات مشروعك لتعديل الإجابة، ولا تنقل المثال باعتباره دليلاً عن عملائك.';
        }
        [$variable, $fixed, $output, $op, $a, $b, $target, $unit] = $row['metric'];
        $formula = ['percent' => 'الحجم × النسبة ÷ 100', 'multiply' => 'عدد الوحدات × مقدار الوحدة', 'divide' => 'المقدار المتاح ÷ مقدار الوحدة'][$op];
        $rule = ['key' => 'estimate', 'label' => $output, 'op' => $op, 'a' => 'volume', 'b' => 'factor', 'round' => 1, 'unit' => $unit];
        $labFields = [
            ['key' => 'volume', 'label' => $variable, 'type' => 'number', 'default' => $a, 'min' => 0, 'required' => true],
            ['key' => 'factor', 'label' => $fixed, 'type' => 'number', 'default' => $b, 'min' => $op === 'divide' ? 0.01 : 0, 'max' => $op === 'percent' ? 100 : 1000000000, 'required' => true],
            ['key' => 'target', 'label' => 'الهدف التجريبي: '.$output, 'type' => 'number', 'default' => $target, 'min' => 0.01, 'required' => true],
        ];
        $questions = [];
        foreach ($labFields as &$field) {
            if (isset($row['sources'][$field['key']])) {
                $field['source'] = $row['sources'][$field['key']];
                $field['hint'] = 'نقطة بداية من ملف المشروع؛ اختر منها مبلغ التجربة الذي تستطيع تحمله، ولا يلزم صرف السقف كله.';
            }
        }
        unset($field);
        $judgment = [];
        foreach ($row['decisions'] as $i => [$prompt, $best, $wrong, $why, $risk]) {
            $options = [
                ['label' => $best, 'is_best' => true, 'feedback' => $why],
                ['label' => $wrong, 'is_best' => false, 'feedback' => $risk],
                ['label' => 'أنقل قرار المثال التدريبي إلى مشروعي دون تحقق', 'is_best' => false, 'feedback' => 'المثال يشرح طريقة التفكير ولا يثبت تشابه جمهورك أو مواردك. '.$row['mistake']],
            ];
            if ($i % 2 === 0) {
                $options = array_reverse($options);
            }
            $questions[] = ['prompt' => $prompt, 'scenario' => $row['example'], 'options' => $options];
            $judgment[] = $why.' '.$risk;
        }
        $template = '# '.$row['asset']." — {{project_name}}\n\n".implode("\n\n", $sections)
            ."\n\n## اختبار الجدوى\n\n| المؤشر | القيمة |\n|---|---|\n| ".$variable.' | {{volume}} |'."\n| ".$fixed.' | {{factor}} |'."\n| ".$output.' | {{estimate}} '.$unit.' |'."\n| الهدف التجريبي | {{target}} ".$unit.' |'
            ."\n\nقاعدة الحساب: ".$formula.'. هذه مدخلات تدريبية قابلة للتغيير وليست متوسطات سوقية أو توقعاً مضموناً.'
            ."\n\n## التنفيذ والتحقق\n\n".$row['action'];
        if ($index === 5) {
            $template .= "\n\n## تركيب أصول المسار\n\nراجع الأصول المحفوظة المرفقة أدناه، وحل التعارض بين قراراتها قبل اعتماد هذه النسخة. إذا غاب أصل فأنجزه من رابط الدرس السابق؛ الحقول الحالية لا تعوّض الدليل الناقص.";
        }

        return [
            'slug' => $row['slug'], 'title' => $row['title'], 'outcome_type' => $row['type'] ?? str_replace('-', '_', $row['slug']),
            'outcome_title' => $row['asset'], 'objective' => 'أنشئ '.$row['asset'].' لمشروعك، ثم '.$row['action'],
            'quick_summary' => $row['principle'],
            'concept' => "## الفكرة التي تحكم القرار\n\n".$row['principle']."\n\n".$course['context']
                ."\n\n## خطوات بناء الأصل\n\n".implode("\n\n", $practice)
                ."\n\n## كيف تراجع قرارك\n\n".implode("\n\n", $judgment)
                ."\n\n## حدود الحساب والتطبيق\n\n".$output.' يُحسب هنا بقاعدة: '.$formula.'. غيّر '.$variable.' وحده في شريط ماذا لو مع تثبيت بقية المدخلات، ثم قارن بالهدف الذي كتبته. الأرقام الافتراضية افتراضات تعليمية، وليست معياراً للسوق العربي. لا تستنتج السببية من محاكاة حسابية؛ سجّل النتيجة الواقعية والفارق وسبباً محتملاً قبل إعادة القرار. '.$row['action'],
            'common_mistake' => 'في مشروع «{{project_name}}»: '.$row['mistake'], 'market_example' => 'مثال افتراضي للتدريب من السوق العربي، وليس دراسة حالة موثّقة: '.$row['example'],
            'scenario_template' => 'تطبّق هذا القرار على «{{project_name}}» وعرضه {{offer}} وجمهوره {{audience}}. استبدل تفاصيل الحالة الافتراضية بأدلتك، واحتفظ بما لم تتحقق منه كفرضية.',
            'general_scenario' => ['project_name' => $course['project'].' (تدريبي افتراضي)', 'offer' => $course['offer'], 'audience' => $course['audience'], 'sector' => 'services', 'city' => $course['city'], 'budget' => ($row['sources']['volume'] ?? null) === 'budget' ? $a : 3000, 'channel' => 'instagram', 'goal' => 'leads', 'goal_number' => 30],
            'guided_steps' => $guided,
            'lab' => ['fields' => $labFields, 'computed' => [$rule], 'template' => $template],
            'whatif' => ['variable' => ['key' => 'volume', 'label' => $variable, 'unit' => '', 'min' => 1, 'max' => max(100, $a * 4), 'step' => 1, 'default' => $a],
                'outputs' => [$rule], 'target' => ['key' => 'target', 'label' => $output, 'compare' => 'estimate', 'direction' => $row['direction'] ?? 'at_least', 'edge' => .8],
                'explain' => $formula.'. افتراض تدريبي قابل للتعديل؛ لا يمثل متوسطاً قطاعياً. ثبات '.$fixed.' افتراض في هذه المحاكاة فقط.'],
            'opening' => ['prompt' => $row['opening'], 'scenario' => $row['example'], 'options' => $questions[0]['options']],
            'questions' => $questions, 'needs' => $needs,
            'real_action' => $row['action'],
            'real_result_fields' => [
                ['key' => 'actual', 'label' => 'النتيجة الفعلية: '.$output, 'type' => 'number'],
                ['key' => 'evidence', 'label' => 'مصدر النتيجة وتاريخ القياس', 'type' => 'text'],
                ['key' => 'decision', 'label' => 'التعديل التالي وسبب الفارق عن الفرضية', 'type' => 'text'],
            ],
        ];
    }
}
