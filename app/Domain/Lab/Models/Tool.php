<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['input_schema' => 'array', 'is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
