<?php

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;

class ExperimentExposure extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
