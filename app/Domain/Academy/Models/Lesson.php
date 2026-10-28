<?php

namespace App\Domain\Academy\Models;

use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $guarded = [];

    /** The eight parts of the full lesson form (blueprint §4.5) plus the opening decision. Keys are stable: events, signals and the studio use them. */
    public const PARTS = [
        'opening' => 'قرارك الأول',
        'goal' => 'الهدف وما يدخل',
        'concept' => 'المفهوم',
        'situation' => 'موقفك',
        'guided' => 'التدريب الموجّه',
        'lab' => 'المختبر + ماذا لو',
        'questions' => 'أسئلة القرار',
        'template' => 'القالب',
        'apply' => 'طبّق في الواقع',
    ];

    protected function casts(): array
    {
        return [
            'general_scenario' => 'array', 'guided_steps' => 'array', 'lab_config' => 'array', 'whatif_rules' => 'array',
            'real_result_fields' => 'array', 'published_at' => 'datetime', 'is_preview' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('position');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function assetLinks(): HasMany
    {
        return $this->hasMany(LessonAssetLink::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ResourceTemplate::class, 'template_id');
    }

    public function labTool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'lab_tool_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LessonVersion::class)->orderByDesc('version');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Which of the parts have real content; the studio and the pipeline heat-map use this. */
    public function partCompleteness(): array
    {
        $questions = $this->relationLoaded('questions') ? $this->questions : $this->questions()->get();

        return [
            'opening' => $questions->where('is_opening', true)->isNotEmpty(),
            'goal' => filled($this->goal),
            'concept' => filled($this->concept_body) && str_word_count_utf8($this->concept_body) >= 300,
            'situation' => filled($this->scenario_template),
            'guided' => count($this->guided_steps ?? []) >= 3,
            'lab' => ! empty($this->lab_config['fields']) && ! empty($this->lab_config['template']) && ! empty($this->whatif_rules),
            'questions' => $questions->where('is_opening', false)->count() >= 3,
            'template' => $this->template_id !== null,
            'apply' => filled($this->real_action_template),
        ];
    }
}
