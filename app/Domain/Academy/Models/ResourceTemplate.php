<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['template' => 'array', 'is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
