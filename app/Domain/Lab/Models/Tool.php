<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * المختبر = tools (blueprint terminology). kind ∈ generator | calculator | simulator.
 * credit_cost is the price of the optional AI enhancement; the base generation is always free (0 بلا AI).
 */
class Tool extends Model
{
    protected $guarded = [];

    public const KINDS = ['generator' => 'مولّد', 'calculator' => 'حاسبة', 'simulator' => 'محاكي'];

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
