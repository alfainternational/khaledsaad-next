<?php

namespace App\Domain\Assessment\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }
}
