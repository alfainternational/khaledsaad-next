<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * المختبر = tools (blueprint terminology). kind ∈ generator | calculator | simulator | panel | studio.
 * credit_cost is the price of the optional AI enhancement; the base generation is always free (0 بلا AI).
 * A panel has no free base output — it is model voices or nothing — and its credit_cost is per voice-round, not per run.
 */
class Tool extends Model
{
    protected $guarded = [];

    public const KINDS = ['generator' => 'مولّد', 'calculator' => 'حاسبة', 'simulator' => 'محاكي', 'panel' => 'لوحة', 'studio' => 'استوديو'];

    public const CATEGORIES = ['التخطيط', 'الجمهور', 'المحتوى', 'الإعلانات', 'القياس', 'الذكاء الاصطناعي'];

    protected function casts(): array
    {
        return [
            'input_schema' => 'array', 'is_published' => 'boolean', 'feeds_from' => 'array', 'feeds_into' => 'array',
            'quality_rules' => 'array', 'example_input' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ToolVersion::class)->orderByDesc('version');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ToolOutput::class);
    }

    public function activeVersion(): ?ToolVersion
    {
        return $this->versions()->where('is_active', true)->orderByDesc('version')->first();
    }

    public function isGenerator(): bool
    {
        return $this->kind === 'generator';
    }

    public function isPanel(): bool
    {
        return $this->kind === 'panel';
    }

    public function isStudio(): bool
    {
        return $this->kind === 'studio';
    }

    /**
     * Whether this kind renders its output through a stored Markdown template with versions. Calculators and the
     * simulator compute their output in code; a generator and a panel both fill a template, so the tool studio
     * must edit theirs — and must never blank it.
     */
    public function usesTemplate(): bool
    {
        return in_array($this->kind, ['generator', 'panel', 'studio'], true);
    }

    /** The field definitions (key, label, type, source, from_asset…) the form and the engine read. */
    public function fields(): array
    {
        return array_values(array_filter($this->input_schema ?? [], fn ($f) => is_array($f) && isset($f['key'])));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
