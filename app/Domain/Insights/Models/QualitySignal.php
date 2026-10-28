<?php

namespace App\Domain\Insights\Models;

use Illuminate\Database\Eloquent\Model;

class QualitySignal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'computed_at' => 'datetime'];
    }
}
