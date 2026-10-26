<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;

class ToolOutput extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
