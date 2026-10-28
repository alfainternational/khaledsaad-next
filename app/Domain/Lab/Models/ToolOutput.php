<?php

namespace App\Domain\Lab\Models;

use App\Domain\Academy\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolOutput extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'quality_report' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class, 'tool_run_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
