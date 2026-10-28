<?php

namespace App\Domain\Academy\Services;

/**
 * شريط «ماذا لو؟» (blueprint §4.5 principle 4): one variable, declared sector benchmarks, verdict against the learner's goal
 * (ضمن الهدف / على الحافة / خارج الهدف).
 *
 * rules = [
 *   'variable'  => ['key','label','unit','min','max','step'],
 *   'benchmark' => ['label','note','op'=>'lookup','table'=>[...],'arg'=>'channel','default'=>60,'key'=>'benchmark'] | ['key','value',...],
 *   'outputs'   => [ {key,label,op,...}, ... ]   (RuleEvaluator rules, evaluated in order)
 *   'target'    => ['key' => 'goal_number', 'label' => 'هدفك', 'compare' => 'results', 'direction' => 'at_least'|'at_most', 'edge' => 0.7]
 *   'explain'   => 'القاعدة المُعلنة'
 * ]
 */
class WhatIfEngine
{
    public function __construct(private readonly RuleEvaluator $evaluator) {}

    public function evaluate(array $rules, array $values, mixed $variableValue = null): array
    {
        $variable = $rules['variable'] ?? null;
        if ($variable && $variableValue !== null) {
            $values[$variable['key']] = $this->clamp((float) $variableValue, $variable);
        } elseif ($variable && ! isset($values[$variable['key']])) {
            $values[$variable['key']] = $variable['default'] ?? $variable['min'] ?? 0;
        }
        $computed = [];
        if (! empty($rules['benchmark'])) {
            $computed[] = ['key' => 'benchmark'] + $rules['benchmark'];
        }
        foreach ($rules['outputs'] ?? [] as $out) {
            $computed[] = $out;
        }
        $bag = $this->evaluator->compute($computed, $values);

        $outputs = [];
        foreach ($rules['outputs'] ?? [] as $out) {
            $outputs[] = ['key' => $out['key'], 'label' => $out['label'] ?? $out['key'], 'value' => $bag[$out['key']] ?? null, 'display' => RuleEvaluator::fmt($bag[$out['key']] ?? 0).(isset($out['unit']) ? ' '.$out['unit'] : '')];
        }

        $verdict = null;
        $target = $rules['target'] ?? null;
        if ($target) {
            $goal = (float) $this->num($bag[$target['key']] ?? 0);
            $actual = (float) $this->num($bag[$target['compare']] ?? 0);
            $verdict = $this->verdict($actual, $goal, $target['direction'] ?? 'at_least', (float) ($target['edge'] ?? 0.7));
        }

        return [
            'variable' => $variable ? ['key' => $variable['key'], 'value' => $values[$variable['key']] ?? null, 'label' => $variable['label'] ?? '', 'unit' => $variable['unit'] ?? '', 'min' => $variable['min'] ?? 0, 'max' => $variable['max'] ?? 100, 'step' => $variable['step'] ?? 1] : null,
            'benchmark' => isset($bag['benchmark']) ? ['label' => $rules['benchmark']['label'] ?? 'المعيار المرجعي', 'value' => $bag['benchmark'], 'display' => RuleEvaluator::fmt($bag['benchmark']).' '.($rules['benchmark']['unit'] ?? ''), 'note' => $rules['benchmark']['note'] ?? null] : null,
            'outputs' => $outputs,
            'target' => $target ? ['label' => $target['label'] ?? 'هدفك', 'value' => $bag[$target['key']] ?? null, 'display' => RuleEvaluator::fmt($bag[$target['key']] ?? 0)] : null,
            'verdict' => $verdict,
            'verdict_label' => ['within' => 'ضمن الهدف', 'edge' => 'على الحافة', 'outside' => 'خارج الهدف'][$verdict] ?? null,
            'explain' => $rules['explain'] ?? null,
            'values' => $bag,
        ];
    }

    private function verdict(float $actual, float $goal, string $direction, float $edge): string
    {
        if ($goal <= 0) {
            return 'edge';
        }
        if ($direction === 'at_most') {
            if ($actual <= $goal) {
                return 'within';
            }

            return $actual <= $goal / max($edge, .01) ? 'edge' : 'outside';
        }
        if ($actual >= $goal) {
            return 'within';
        }

        return $actual >= $goal * $edge ? 'edge' : 'outside';
    }

    private function clamp(float $value, array $variable): float
    {
        $min = (float) ($variable['min'] ?? 0);
        $max = (float) ($variable['max'] ?? PHP_FLOAT_MAX);

        return max($min, min($max, $value));
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : (float) preg_replace('/[^\d.]/', '', (string) $v);
    }
}
