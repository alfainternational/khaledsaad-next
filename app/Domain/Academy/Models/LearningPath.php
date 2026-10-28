<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningPath extends Model
{
    protected $guarded = [];

    public const AUDIENCES = ['owner' => 'أصحاب المشاريع', 'marketer' => 'المسوّقون والوكالات'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')->withPivot('position')->orderByPivot('position');
    }
}
