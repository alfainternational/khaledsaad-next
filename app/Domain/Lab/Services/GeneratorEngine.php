<?php

namespace App\Domain\Lab\Services;

use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolVersion;
use App\Support\Markdown;
use Illuminate\Support\Str;

/**
 * The one generation engine (blueprint §4.7 and stage-2 rule "no two logic paths"): normalises the input against the tool's
 * field schema, derives values (labels, numbers, per-tool recipe rows), fills the template of the active/experiment version
 * and runs the executability check. The lesson lab renders through renderTemplate() too.
 */
class GeneratorEngine
{
    public function __construct(private readonly QualityChecker $quality, private readonly Recipes $recipes) {}

    /** @return array{markdown:string, values:array, input:array, quality:array, title:string, missing:array} */
    public function generate(Tool $tool, array $rawInput, ?ToolVersion $version = null): array
    {
        // The tool's live field schema is the single source of truth; a version carries the template (what A/B compares), not the fields.
        $fields = $tool->fields();
        $input = $this->normalise($fields, $rawInput);
        $missing = $this->missing($fields, $input);
        $values = $this->values($input, $fields);
        $values = array_merge($values, $this->recipes->compute($tool->slug, $values, $input));
        $template = $version?->output_template ?: (string) $tool->output_template;
        $markdown = $this->renderTemplate($template, $values);
        $title = $this->title($markdown, $tool->name);

        return [
            'markdown' => $markdown, 'values' => $values, 'input' => $input, 'title' => $title, 'missing' => $missing,
            'quality' => $this->quality->check($markdown, $values, $tool->quality_rules ?? [], $fields),
        ];
    }

    /** Fill a Markdown template with values (shared with the lesson lab so there is exactly one filler). */
    public function renderTemplate(string $template, array $values): string
    {
        $values['date'] = $values['date'] ?? now()->format('Y/m/d');

        return trim(Markdown::fill($template, $values));
    }

    public function normalise(array $fields, array $raw): array
    {
        $input = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $raw[$key] ?? null;
            if (($field['type'] ?? 'text') === 'number') {
                $input[$key] = is_numeric($value) ? (float) $value + 0 : (isset($field['default']) && ($value === null || $value === '') ? $field['default'] : null);
                if (is_float($input[$key]) && floor($input[$key]) === $input[$key]) {
                    $input[$key] = (int) $input[$key];
                }

                continue;
            }
            if (($field['type'] ?? '') === 'select') {
                $options = LabCatalog::options($field);
                $value = is_string($value) && isset($options[$value]) ? $value : ($field['default'] ?? array_key_first($options));
                $input[$key] = $value;

                continue;
            }
            $input[$key] = is_string($value) ? trim(mb_substr(str_replace("\r\n", "\n", $value), 0, 1500)) : (isset($field['default']) ? (string) $field['default'] : '');
        }

        return $input;
    }

    public function missing(array $fields, array $input): array
    {
        $missing = [];
        foreach ($fields as $field) {
            $value = $input[$field['key']] ?? null;
            if (! empty($field['required']) && ($value === null || trim((string) $value) === '')) {
                $missing[$field['key']] = $field['label'];
            }
        }

        return $missing;
    }

    /** Labels, short forms and formatted numbers every template can use. */
    public function values(array $input, array $fields): array
    {
        $values = $input;
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'select') {
                $options = LabCatalog::options($field);
                $values[$field['key'].'_label'] = $options[$input[$field['key']] ?? ''] ?? '—';
            }
            if (($field['type'] ?? '') === 'number' && is_numeric($input[$field['key']] ?? null)) {
                $values[$field['key'].'_fmt'] = number_format((float) $input[$field['key']]);
            }
            if (is_string($input[$field['key']] ?? null)) {
                $values[$field['key'].'_short'] = Str::limit($input[$field['key']], 60, '…');
            }
        }
        foreach (['channel' => Catalog::CHANNELS, 'goal' => Catalog::GOALS, 'sector' => Catalog::SECTORS] as $key => $map) {
            if (isset($input[$key]) && isset($map[$input[$key]])) {
                $values[$key.'_label'] = $map[$input[$key]];
            }
        }
        $values['date'] = now()->format('Y/m/d');

        return $values;
    }

    private function title(string $markdown, string $fallback): string
    {
        return preg_match('/^#\s+(.+)$/mu', $markdown, $m) ? trim(preg_replace('/\s+—\s+.*$/u', '', $m[1]) ?? $m[1]) : $fallback;
    }
}
