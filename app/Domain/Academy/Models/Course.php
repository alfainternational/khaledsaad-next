<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function publishedLessons(): HasMany
    {
        return $this->lessons()->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')->withPivot('position');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'prerequisite_course_id');
    }

    public function next(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'next_course_id');
    }
}
