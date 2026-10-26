<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;

class ToolVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['input_schema' => 'array', 'output_schema' => 'array', 'is_active' => 'boolean'];
    }
}
