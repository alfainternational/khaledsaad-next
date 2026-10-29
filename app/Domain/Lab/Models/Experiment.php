<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A/B experiment (blueprint §6.1 item 5 and §6.4): one running at a time per subject, measured by save rate. */
class Experiment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ExperimentVariant::class);
    }

    public function exposures(): HasMany
    {
        return $this->hasMany(ExperimentExposure::class);
    }
}
