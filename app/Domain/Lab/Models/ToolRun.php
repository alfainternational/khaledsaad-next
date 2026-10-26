<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;

class ToolRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['input_payload' => 'array', 'output_payload' => 'array'];
    }
}
