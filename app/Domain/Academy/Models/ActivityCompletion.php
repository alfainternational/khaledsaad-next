<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityCompletion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'deliverable' => 'array'];
    }
}
