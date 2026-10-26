<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'answer_key' => 'array'];
    }
}
