<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;

class LessonVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
