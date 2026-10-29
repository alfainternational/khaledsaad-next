<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A version of the generation template of a tool (استوديو الأداة §6.4); A/B experiments pick between two of them. */
class ToolVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['input_schema' => 'array', 'output_schema' => 'array', 'is_active' => 'boolean'];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
