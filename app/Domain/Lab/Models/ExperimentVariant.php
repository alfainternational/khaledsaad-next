<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentVariant extends Model
{
    protected $guarded = [];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    public function toolVersion(): BelongsTo
    {
        return $this->belongsTo(ToolVersion::class);
    }
}
